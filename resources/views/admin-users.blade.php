<!DOCTYPE html>
<html>
<head>
    <title>VitalVida Admin - User Management</title>
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
        .back-link {
            color: #007cba;
            text-decoration: none;
            margin-bottom: 10px;
            display: inline-block;
        }
        .users-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: bold;
            color: #333;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .status.active {
            background: #e8f5e8;
            color: #2e7d32;
        }
        .status.inactive {
            background: #ffebee;
            color: #d32f2f;
        }
        .pagination {
            margin-top: 20px;
            text-align: center;
        }
        .pagination a {
            padding: 8px 12px;
            margin: 0 4px;
            background: white;
            color: #007cba;
            text-decoration: none;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .pagination a:hover {
            background: #f0f8ff;
        }
        .pagination .current {
            background: #007cba;
            color: white;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="/admin" class="back-link">← Back to Dashboard</a>
        <h1>User Management</h1>
    </div>
    
    <div class="users-table">
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                <tr>
                    <td>{{ $user->name }}</td>
                    <td>{{ $user->email }}</td>
                    <td>{{ ucfirst($user->role ?? 'user') }}</td>
                    <td>
                        <span class="status {{ $user->email_verified_at ? 'active' : 'inactive' }}">
                            {{ $user->email_verified_at ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td>{{ $user->created_at->format('M d, Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    
    <div class="pagination">
        {{ $users->links() }}
    </div>
</body>
</html> 