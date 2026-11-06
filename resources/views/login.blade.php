<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión</title>
    <style>
        body {
            font-family: system-ui, sans-serif;
            background: #f4f5f7;
            margin: 0;
            padding: 24px;
            display: flex;
            justify-content: center;
        }
        .card {
            background: #fff;
            border-radius: 8px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,.1);
            width: 100%;
            max-width: 360px;
            margin-top: 10vh;
        }
        h1 { margin-top: 0; font-size: 22px; }
        label { display: block; color: #666; font-size: 14px; margin: 12px 0 4px; }
        input[type=email], input[type=password] {
            width: 100%;
            box-sizing: border-box;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 16px;
        }
        .remember { display: flex; align-items: center; gap: 6px; margin-top: 12px; }
        .remember label { margin: 0; }
        button {
            width: 100%;
            margin-top: 16px;
            padding: 10px;
            border: 0;
            border-radius: 6px;
            background: #2563eb;
            color: #fff;
            font-size: 16px;
            cursor: pointer;
        }
        .error { color: #b91c1c; font-size: 14px; margin-top: 8px; }
    </style>
</head>
<body>
    <form class="card" method="POST" action="{{ route('login') }}">
        @csrf
        <h1>Iniciar sesión</h1>
        <label for="email">Correo</label>
        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username">
        <label for="password">Contraseña</label>
        <input id="password" type="password" name="password" required autocomplete="current-password">
        <div class="remember">
            <input id="remember" type="checkbox" name="remember">
            <label for="remember">Recordarme</label>
        </div>
        @error('email')
            <div class="error">{{ $message }}</div>
        @enderror
        <button type="submit">Entrar</button>
    </form>
</body>
</html>
