<?php

namespace Tests\Feature;

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Tests\TestCase;

/**
 * Pruebas obligatorias del TP "Logging y detección de incidentes" (punto 8).
 */
class SecurityLoggingTest extends TestCase
{
    use RefreshDatabase;

    private TestHandler $securityLog;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Captura en memoria lo que se escribe en el canal "security"
        $this->securityLog = new TestHandler;
        Log::channel('security')->getLogger()->setHandlers([$this->securityLog]);

        $this->user = User::factory()->create([
            'email' => 'prueba@local.test',
            'password' => 'clave-correcta',
        ]);
    }

    private function attemptLogin(string $password = 'incorrecta')
    {
        return $this->post('/login', [
            'email' => 'prueba@local.test',
            'password' => $password,
        ]);
    }

    private function failLogin(int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->attemptLogin();
        }
    }

    public function test_login_valido_registra_info_y_success(): void
    {
        $this->attemptLogin('clave-correcta')->assertRedirect('/servidor');

        $this->assertAuthenticatedAs($this->user);
        $this->assertSame(1, LoginAttempt::where('status', 'success')->count());
        $this->assertTrue($this->securityLog->hasRecordThatContains('Authentication success', Level::Info));
    }

    public function test_dos_contrasenas_incorrectas_registran_dos_warning_y_dos_failed(): void
    {
        $this->failLogin(2);

        $this->assertGuest();
        $this->assertSame(2, LoginAttempt::where('status', 'failed')->count());
        $warnings = array_filter(
            $this->securityLog->getRecords(),
            fn ($r) => $r->level === Level::Warning && $r->message === 'Authentication failed'
        );
        $this->assertCount(2, $warnings);
        // El hash no guarda el correo en texto plano
        $this->assertSame(hash('sha256', 'prueba@local.test'), LoginAttempt::first()->identifier_hash);
    }

    public function test_tres_fallos_generan_alert_en_el_intento_siguiente(): void
    {
        $this->failLogin(3);
        $this->assertFalse($this->securityLog->hasAlertRecords());

        $this->attemptLogin();

        $this->assertTrue($this->securityLog->hasRecordThatContains('Possible brute force', Level::Alert));
    }

    public function test_cinco_fallos_bloquean_10_minutos_en_el_intento_siguiente(): void
    {
        $this->failLogin(5);

        $this->attemptLogin()->assertStatus(429);

        $this->assertTrue($this->securityLog->hasRecordThatContains('Temporary IP block', Level::Critical));
        $this->assertTrue(Cache::has('security:block:'.hash('sha256', '127.0.0.1')));
        // Ni con la contraseña correcta se puede entrar mientras dura el bloqueo
        $this->attemptLogin('clave-correcta')->assertStatus(429);
        $this->assertGuest();
    }

    public function test_ip_bloqueada_recibe_429_y_registra_blocked(): void
    {
        Cache::put('security:block:'.hash('sha256', '127.0.0.1'), true, now()->addMinutes(10));

        $this->attemptLogin('clave-correcta')->assertStatus(429);

        $this->assertSame(1, LoginAttempt::where('status', 'blocked')->count());
        $this->assertTrue($this->securityLog->hasRecordThatContains('Blocked login attempt', Level::Warning));
    }

    public function test_despues_de_diez_minutos_vuelve_a_admitirse_el_intento(): void
    {
        $this->failLogin(5);
        $this->attemptLogin()->assertStatus(429);

        $this->travel(11)->minutes();

        $this->attemptLogin('clave-correcta')->assertRedirect('/servidor');
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_panel_de_monitoreo_requiere_permiso(): void
    {
        $this->get('/servidor')->assertRedirect('/login');

        $this->actingAs($this->user)->get('/servidor')->assertForbidden();

        $admin = User::factory()->create();
        $admin->is_admin = true;
        $admin->save();
        $this->actingAs($admin)->get('/servidor')
            ->assertOk()
            ->assertSee('Inicios de sesión exitosos')
            ->assertSee('IPs con 3 o más fallos en 5 minutos');
    }
}
