<header class="admin-header">
    <div class="header-left">
        <div class="breadcrumb-wrapper">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('admin.dashboard') }}">
                            <i class="fas fa-home"></i>
                        </a>
                    </li>
                    @yield('breadcrumb')
                </ol>
            </nav>
        </div>
    </div>
    
    <div class="header-right">
        <div class="header-actions">
            <!-- Notifications -->
            <div class="dropdown">
                <button class="btn btn-link notification-btn" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end notification-dropdown">
                    <li class="dropdown-header">
                        <h6>Notifications</h6>
                        <a href="#" class="text-muted">Mark all as read</a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="notification-item">
                                <div class="notification-icon warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">Security Alert</div>
                                    <div class="notification-text">Multiple failed login attempts detected</div>
                                    <div class="notification-time">2 minutes ago</div>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="notification-item">
                                <div class="notification-icon info">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">System Update</div>
                                    <div class="notification-text">Database backup completed successfully</div>
                                    <div class="notification-time">1 hour ago</div>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="#">
                            <div class="notification-item">
                                <div class="notification-icon success">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">User Activity</div>
                                    <div class="notification-text">New user registered: john.doe@example.com</div>
                                    <div class="notification-time">3 hours ago</div>
                                </div>
                            </div>
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-center" href="#">
                            View all notifications
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- User Menu -->
            <div class="dropdown">
                <button class="btn btn-link user-menu-btn" type="button" data-bs-toggle="dropdown">
                    <div class="user-avatar">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="user-info">
                        <div class="user-name">{{ auth()->user()->name ?? auth()->user()->username }}</div>
                        <div class="user-role">{{ auth()->user()->role }}</div>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end user-dropdown">
                    <li>
                        <a class="dropdown-item" href="#">
                            <i class="fas fa-user"></i>
                            Profile
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item" href="{{ route('admin.settings') }}">
                            <i class="fas fa-cog"></i>
                            Settings
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}" class="d-inline">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header> 