<!DOCTYPE html>
<html>
<head>
    <title>VitalVida Admin</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f5f5f5; 
        }
        .header { 
            background: white; 
            padding: 20px; 
            margin-bottom: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin: 0;
            color: #007cba;
        }
        .stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px; 
            margin-bottom: 20px; 
        }
        .stat-card { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #007cba;
        }
        .nav {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .nav a {
            display: inline-block;
            margin-right: 20px;
            color: #007cba;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .nav a:hover {
            background: #f0f8ff;
        }
        .logout {
            color: #dc3545 !important;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>�� VitalVida Enterprise Admin</h1>
        <p>Welcome to your comprehensive business management system</p>
    </div>
    
    <div class="stats">
        <div class="stat-card">
            <div class="stat-number">17</div>
            <div>Active Portals</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">24/7</div>
            <div>System Status</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">100%</div>
            <div>Uptime</div>
        </div>
        <div class="stat-card">
            <div class="stat-number">Live</div>
            <div>Real-time Data</div>
        </div>
    </div>
    
    <div class="nav">
        <a href="/admin/ceo">👔 CEO Dashboard</a>
        <a href="/admin/gm">⚙️ General Manager</a>
        <a href="/admin/accountant">📊 Accountant</a>
        <a href="/admin/logistics">🚚 Logistics</a>
        <a href="/admin/inventory">📋 Inventory</a>
        <a href="/admin/telesales">�� Telesales</a>
        <a href="/admin/kyc">🔍 KYC Portal</a>
        <a href="/admin/investor">💰 Investor</a>
        <a href="/admin/users">👥 Manage Users</a>
        <a href="/admin/settings">⚙️ Settings</a>
        <a href="/logout" class="logout">🚪 Logout</a>
    </div>
</body>
</html>
