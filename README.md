# Monitor de Servidor con Laravel

Trabajo práctico de Programación IV (UTN FRRe, cohorte 2026). La app lee datos del sistema operativo Windows y los muestra en una vista web (`/servidor`) y en una API JSON (`/api/metrics`). La vista se actualiza sola cada 5 segundos (polling).

## Entorno usado

| | |
|---|---|
| Sistema operativo | Microsoft Windows 11 Pro (`php_uname`: Windows NT 10.0) |
| PHP | 8.3.33 |
| Laravel | 13.39.0 |
| Base de datos | SQLite |

## Cómo ejecutarlo

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Abrir `http://localhost:8000/servidor`. El panel requiere iniciar sesión con un usuario administrador (ver [Seguridad](#seguridad)). Para crear uno:

```sh
php artisan tinker
$u = new App\Models\User(['name' => 'Admin', 'email' => 'admin@ejemplo.com', 'password' => 'una-clave-segura']);
$u->is_admin = true;
$u->save();
```

Usar `localhost:8000` y no otro host o puerto: la API usa la sesión del navegador a través de Sanctum, que solo la acepta desde los dominios configurados en `SANCTUM_STATEFUL_DOMAINS` (por defecto incluye `localhost:8000`).

## Estructura

| Parte | Archivo | Responsabilidad |
|---|---|---|
| Service | `app/Services/ServerMetrics.php` | Lee el sistema operativo (PHP + PowerShell/WMI) |
| Controlador | `app/Http/Controllers/ServerController.php` | Devuelve la vista o el JSON |
| Vista | `resources/views/servidor.blade.php` | Tarjetas + polling cada 5 s |
| Rutas | `routes/web.php`, `routes/api.php` | `/servidor` y `/api/metrics` |

## Capturas

- Vista `/servidor`: [evidencias/capturas/01-servidor-panel.png](evidencias/capturas/01-servidor-panel.png)
- Respuesta de `/api/metrics`: [evidencias/capturas/02-api-metrics.png](evidencias/capturas/02-api-metrics.png)

## Respuestas

**1. ¿Por qué la temperatura suele aparecer como "No disponible" en Windows?**

La clase `MSAcpi_ThermalZoneTemperature` está en el espacio de nombres `root/wmi`, y Windows solo permite leerla con permisos de administrador. Como `php artisan serve` corre con un usuario normal, la consulta falla, el `try/catch` del script la ignora y el valor queda en `null`. Aun ejecutando como administrador, muchas placas madre no informan la temperatura por ACPI, o informan un valor fijo de la zona térmica que no es la temperatura real de la CPU. En este equipo, sin permisos de administrador, el valor fue `null`.

**2. ¿Por qué el script de PowerShell nunca debe incluir datos que vengan del usuario?**

Porque `shell_exec()` ejecuta el texto como un comando en el servidor, con los permisos del proceso de PHP. Si se arma el script con datos de un formulario o de la URL, un atacante puede cerrar el comando previsto y agregar el suyo (por ejemplo `; Remove-Item ...` o descargar y ejecutar un programa). Eso es **inyección de comandos** y da control del servidor. Por eso el script es fijo (heredoc con comillas simples `<<<'PS'`, que no interpola variables) y se pasa codificado con `-EncodedCommand`.

**3. ¿Qué riesgo tiene dejar `/api/metrics` sin autenticación en un servidor real?**

- **Reconocimiento:** expone el nombre del equipo, la versión del sistema operativo y la versión exacta de PHP. Con eso un atacante busca vulnerabilidades conocidas para esas versiones.
- **Información operativa:** el uso de CPU, memoria y disco, y el uptime (cuándo se reinició, es decir, si se aplicaron parches) ayudan a elegir el momento de un ataque.
- **Denegación de servicio:** cada pedido abre un proceso de PowerShell, que es costoso. Muchos pedidos simultáneos pueden saturar el servidor. El `throttle:60,1` limita por cliente, pero no frena un ataque desde muchas IPs.

En este proyecto la ruta está protegida con `auth:sanctum` y el permiso `can:view-security` (solo administradores), además del `throttle`.

**4. Con polling cada 5 segundos, ¿cuántos pedidos por minuto recibe el servidor si hay 50 personas mirando?**

Cada persona hace 60 / 5 = 12 pedidos por minuto, así que 50 × 12 = **600 pedidos por minuto** (10 por segundo), y cada uno abre PowerShell, aunque los datos no hayan cambiado.

**5. Si una app solo necesita la temperatura, ¿qué datos recibe de más al llamar a `/api/metrics`?**

Recibe todo lo demás: `hostname`, `os`, `php`, `disk` (`total_gb`, `free_gb`, `used_pct`), `memory` (`total_mb`, `available_mb`, `used_pct`), `cpu_pct`, `uptime_s` y `at`. Es decir, 12 valores de más para usar 1 (`cpu_temp_c`). Esto se llama **over-fetching**, y es el problema que resuelve GraphQL al dejar que el cliente pida solo los campos que necesita.

## Seguridad

Además del TP del monitor, el proyecto incluye el TP "Logging y detección de incidentes":

- Login propio (`app/Http/Controllers/LoginController.php`) en `/login`.
- Registro de intentos de autenticación en la tabla `login_attempts` y en el canal de log `security` (`storage/logs/security-AAAA-MM-DD.log`).
- Middleware `SecurityLoginGuard` en `POST /login`: alerta a los 3 fallos y bloquea la IP 10 minutos a los 5 (HTTP 429).
- Panel `/servidor` y API restringidos a usuarios con `is_admin` mediante el Gate `view-security`.

El informe y las evidencias están en [evidencias/](evidencias/). Los tests se ejecutan con `php artisan test`.
