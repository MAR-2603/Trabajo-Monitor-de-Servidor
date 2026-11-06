<?php
namespace App\Http\Controllers;
use App\Models\LoginAttempt;
use App\Services\ServerMetrics;
class ServerController extends Controller
{
// Laravel crea el ServerMetrics y lo pasa solo (inyección de dependencias)
public function index(ServerMetrics $metrics)
{
// Indicadores de autenticación de las últimas 24 horas
$since = now()->subDay();
$stats = [];
foreach (['success', 'failed', 'blocked'] as $status) {
    $stats[$status] = LoginAttempt::where('status', $status)
        ->where('created_at', '>=', $since)->count();
}
// IPs con 3 o más fallos en los últimos 5 minutos (mismo umbral que SecurityLoginGuard)
$suspiciousIps = LoginAttempt::selectRaw('ip_address, COUNT(*) as failures, MAX(created_at) as last_attempt')
    ->where('status', 'failed')
    ->where('created_at', '>=', now()->subMinutes(5))
    ->groupBy('ip_address')
    ->havingRaw('COUNT(*) >= 3')
    ->orderByDesc('failures')
    ->get();
return view('servidor', [
    'data' => $metrics->snapshot(),
    'stats' => $stats,
    'suspiciousIps' => $suspiciousIps,
]);
}
public function api(ServerMetrics $metrics)
{
return response()->json($metrics->snapshot());
}
}
