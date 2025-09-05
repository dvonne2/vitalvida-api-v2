<!DOCTYPE html>
<html>
<head>
    <title>User Management</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f8fafc; }
        .header { background: white; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; }
        .nav { padding: 20px; }
        .nav a { margin-right: 20px; color: #3182ce; text-decoration: none; font-weight: 500; }
        .content { padding: 20px; }
        .add-user { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .add-user input, .add-user select { margin-right: 10px; padding: 8px; border: 1px solid #e2e8f0; border-radius: 4px; }
        .btn { background: #3182ce; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; }
        .btn-danger { background: #e53e3e; }
        .users-table { background: white; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f7fafc; font-weight: 600; }
        .success { background: #c6f6d5; color: #22543d; padding: 10px; border-radius: 4px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>User Management</h1>
        <div>{{ auth()->user()->name }}</div>
    </div>
    
    <div class="nav">
        <a href="/business-admin/dashboard">Dashboard</a>
        <a href="/business-admin/users">Manage Users</a>
    </div>
    
    <div class="content">
        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif
        
        <div class="add-user">
            <h3>Add New User</h3>
            <form method="POST" style="margin-top: 15px;">
                @csrf
                <input type="text" name="name" placeholder="Full Name" required>
                <input type="email" name="email" placeholder="Email" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                </select>
                <button type="submit" class="btn">Add User</button>
            </form>
        </div>
        
        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->role ?? 'user' }}</td>
                        <td>{{ $user->created_at->format('M j, Y') }}</td>
                        <td>
                            <form method="POST" action="/business-admin/users/{{ $user->id }}" style="display: inline;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Delete this user?')">Delete</button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px;">
            {{ $users->links() }}
        </div>
    </div>
</body>
</html> 