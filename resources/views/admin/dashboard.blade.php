@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('breadcrumb')
    <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
@endsection

@section('content')
<div class="dashboard-container">
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon users">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3 class="stat-card-number">{{ $stats['total_users'] }}</h3>
                        <p class="stat-card-label">Total Users</p>
                        <div class="stat-card-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+12% from last month</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon active">
                        <i class="fas fa-user-check"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3 class="stat-card-number">{{ $stats['active_users'] }}</h3>
                        <p class="stat-card-label">Active Users</p>
                        <div class="stat-card-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+8% from last month</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon activities">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3 class="stat-card-number">{{ $stats['todays_activities'] }}</h3>
                        <p class="stat-card-label">Today's Activities</p>
                        <div class="stat-card-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+15% from yesterday</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card">
                <div class="stat-card-body">
                    <div class="stat-card-icon security">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div class="stat-card-content">
                        <h3 class="stat-card-number">{{ $stats['security_events'] }}</h3>
                        <p class="stat-card-label">Security Events</p>
                        <div class="stat-card-change negative">
                            <i class="fas fa-arrow-down"></i>
                            <span>-5% from last week</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-xl-8 mb-4">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5 class="chart-card-title">User Activity Overview</h5>
                    <div class="chart-card-actions">
                        <button class="btn btn-sm btn-outline-secondary">Export</button>
                    </div>
                </div>
                <div class="chart-card-body">
                    <canvas id="userActivityChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 mb-4">
            <div class="chart-card">
                <div class="chart-card-header">
                    <h5 class="chart-card-title">System Health</h5>
                </div>
                <div class="chart-card-body">
                    <canvas id="systemHealthChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activity & Quick Actions -->
    <div class="row">
        <div class="col-xl-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Recent Activity</h5>
                    <a href="{{ route('admin.activity-logs') }}" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <div class="activity-list">
                        @forelse($stats['recent_activities'] as $activity)
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">{{ $activity->action }}</div>
                                <div class="activity-meta">
                                    <span class="activity-user">{{ $activity->user ? $activity->user->username : 'System' }}</span>
                                    <span class="activity-time">{{ $activity->timestamp->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>
                        @empty
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x mb-3"></i>
                            <p>No recent activity</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="{{ route('admin.users.create') }}" class="quick-action-item">
                            <div class="quick-action-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="quick-action-content">
                                <h6>Add New User</h6>
                                <p>Create a new user account</p>
                            </div>
                        </a>
                        
                        <a href="{{ route('admin.database.backup') }}" class="quick-action-item">
                            <div class="quick-action-icon">
                                <i class="fas fa-download"></i>
                            </div>
                            <div class="quick-action-content">
                                <h6>Backup Database</h6>
                                <p>Create a database backup</p>
                            </div>
                        </a>
                        
                        <a href="{{ route('admin.settings') }}" class="quick-action-item">
                            <div class="quick-action-icon">
                                <i class="fas fa-cog"></i>
                            </div>
                            <div class="quick-action-content">
                                <h6>System Settings</h6>
                                <p>Configure system settings</p>
                            </div>
                        </a>
                        
                        <a href="{{ route('admin.activity-logs.export') }}" class="quick-action-item">
                            <div class="quick-action-icon">
                                <i class="fas fa-file-export"></i>
                            </div>
                            <div class="quick-action-content">
                                <h6>Export Logs</h6>
                                <p>Export activity logs</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // User Activity Chart
    const userActivityCtx = document.getElementById('userActivityChart').getContext('2d');
    new Chart(userActivityCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Active Users',
                data: [65, 78, 90, 85, 95, 100],
                borderColor: '#4CAF50',
                backgroundColor: 'rgba(76, 175, 80, 0.1)',
                tension: 0.4
            }, {
                label: 'New Users',
                data: [12, 15, 18, 20, 22, 25],
                borderColor: '#2196F3',
                backgroundColor: 'rgba(33, 150, 243, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
    
    // System Health Chart
    const systemHealthCtx = document.getElementById('systemHealthChart').getContext('2d');
    new Chart(systemHealthCtx, {
        type: 'doughnut',
        data: {
            labels: ['Healthy', 'Warning', 'Critical'],
            datasets: [{
                data: [85, 10, 5],
                backgroundColor: ['#4CAF50', '#FF9800', '#F44336'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
});
</script>
@endpush 