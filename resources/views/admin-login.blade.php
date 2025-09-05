<!DOCTYPE html>
<html>
<head>
    <title>VitalVida Admin Login</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            max-width: 400px; 
            margin: 100px auto; 
            padding: 20px; 
            background: #f5f5f5;
        }
        .login-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        input { 
            width: 100%; 
            padding: 12px; 
            margin: 10px 0; 
            border: 1px solid #ddd; 
            border-radius: 4px;
            box-sizing: border-box;
        }
        button { 
            width: 100%; 
            padding: 12px; 
            background: #007cba; 
            color: white; 
            border: none; 
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background: #005a87;
        }
        .error { 
            color: #d32f2f; 
            margin: 10px 0; 
            padding: 10px;
            background: #ffebee;
            border-radius: 4px;
        }
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 30px;
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
            font-size: 24px;
            font-weight: bold;
            color: #007cba;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">VitalVida Admin</div>
        <h2>Admin Login</h2>
        @if($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="/admin-login">
            @csrf
            <input type="email" name="email" placeholder="Email" value="admin@vitalvida.ng" required>
            <input type="password" name="password" placeholder="Password" value="admin123" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html> 