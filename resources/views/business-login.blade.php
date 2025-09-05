<!DOCTYPE html>
<html>
<head>
    <title>Business Admin Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .login-box { background: white; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); width: 400px; }
        h1 { text-align: center; margin-bottom: 30px; color: #1a202c; }
        input { width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 16px; }
        button { width: 100%; padding: 12px; background: #3182ce; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer; }
        button:hover { background: #2c5aa0; }
        .error { color: #e53e3e; margin-bottom: 15px; padding: 10px; background: #fed7d7; border-radius: 4px; }
    </style>
</head>
<body>
    <div class="login-box">
        <h1>Business Admin</h1>
        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST">
            @csrf
            <input type="email" name="email" placeholder="Email" value="admin@vitalvida.ng" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login to Admin</button>
        </form>
    </div>
</body>
</html> 