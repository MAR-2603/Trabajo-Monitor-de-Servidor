<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
class LoginController extends Controller
{
// Muestra el formulario de inicio de sesión
public function show()
{
return view('login');
}
// Auth::attempt dispara los eventos Login/Failed que guarda RecordSecurityAuthentication
public function login(Request $request)
{
$credentials = $request->validate([
    'email' => ['required', 'email', 'max:255'],
    'password' => ['required', 'string'],
]);
if (! Auth::attempt($credentials, $request->boolean('remember'))) {
    // Mensaje genérico: no revela si el correo existe
    return back()
        ->withErrors(['email' => 'Credenciales incorrectas.'])
        ->onlyInput('email');
}
// Evita fijación de sesión
$request->session()->regenerate();
return redirect()->intended('/servidor');
}
public function logout(Request $request)
{
Auth::logout();
$request->session()->invalidate();
$request->session()->regenerateToken();
return redirect()->route('login');
}
}
