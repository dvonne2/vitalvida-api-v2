<aside class="admin-sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <i class="fas fa-shield-alt"></i>
            <span>RBAC Admin</span>
        </div>
        <button class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="{{ route('admin.users.index') }}" class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="fas fa-users"></i>
                    <span>User Management</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="{{ route('admin.activity-logs') }}" class="nav-link {{ request()->routeIs('admin.activity-logs*') ? 'active' : '' }}">
                    <i class="fas fa-history"></i>
                    <span>Activity Logs</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="{{ route('admin.system-logs') }}" class="nav-link {{ request()->routeIs('admin.system-logs*') ? 'active' : '' }}">
                    <i class="fas fa-server"></i>
                    <span>System Logs</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="{{ route('admin.settings') }}" class="nav-link {{ request()->routeIs('admin.settings*') ? 'active' : '' }}">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="{{ route('admin.database') }}" class="nav-link {{ request()->routeIs('admin.database*') ? 'active' : '' }}">
                    <i class="fas fa-database"></i>
                    <span>Database</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
                <div class="user-details">
                    <div class="user-name">{{ auth()->user()->name ?? auth()->user()->username }}</div>
                    <div class="user-role">{{ auth()->user()->role }}</div>
                </div>
            </div>
            
            <form method="POST" action="{{ route('logout') }}" class="logout-form">
                @csrf
                <button type="submit" class="btn btn-link logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </nav>
</aside> 