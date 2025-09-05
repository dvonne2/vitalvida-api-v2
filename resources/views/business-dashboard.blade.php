<!DOCTYPE html>
<html>
<head>
    <title>Business Admin Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; }
        .header { background: white; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .nav { padding: 20px; }
        .nav a { margin-right: 20px; color: #3182ce; text-decoration: none; font-weight: 500; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; padding: 20px; }
        .stat-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .stat-number { font-size: 32px; font-weight: bold; color: #3182ce; }
        .stat-label { color: #718096; margin-top: 5px; }
        .recent-users { background: white; margin: 20px; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .user-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
        .logout-btn { background: #e53e3e; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="header">
        <h1>VitalVida Business Admin</h1>
        <div>
            Welcome, {{ $user_name }}
            <form method="POST" action="/business-admin/logout" style="display: inline;">
                @csrf
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>
    </div>
    
    <div class="nav">
        <a href="/business-admin/dashboard">Dashboard</a>
        <a href="/business-admin/users">Manage Users</a>
    </div>
    
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number">{{ $total_users }}</div>
            <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">{{ $active_users }}</div>
            <div class="stat-label">Active Users</div>
        </div>
    </div>
    
    <div class="recent-users">
        <h3>Recent Users</h3>
        @foreach($recent_users as $user)
            <div class="user-row">
                <span>{{ $user->name }} ({{ $user->email }})</span>
                <span>{{ $user->created_at->format('M j, Y') }}</span>
            </div>
        @endforeach
    </div>
</body>
</html> 