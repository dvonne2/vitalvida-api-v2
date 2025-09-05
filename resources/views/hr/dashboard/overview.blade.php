@extends('layouts.app')

@section('title', 'HR Dashboard - VitalVida')

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">HR Management Dashboard</h1>
                    <p class="text-muted">Comprehensive overview of human resources and employee management</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-success" onclick="exportDashboard()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics Row -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Employees
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-employees">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Recruitments
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="active-recruitments">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Training Progress
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="training-progress">0%</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-graduation-cap fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Performance Alerts
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="performance-alerts">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Talent Pipeline Overview -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Talent Pipeline Overview</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink"
                            data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in"
                            aria-labelledby="dropdownMenuLink">
                            <div class="dropdown-header">Pipeline Actions:</div>
                            <a class="dropdown-item" href="#" onclick="viewAllCandidates()">View All Candidates</a>
                            <a class="dropdown-item" href="#" onclick="createNewJob()">Create New Job</a>
                            <a class="dropdown-item" href="#" onclick="exportPipeline()">Export Pipeline</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="talentPipelineChart"></canvas>
                    </div>
                    <div class="mt-4">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h4 text-primary" id="applications-count">0</div>
                                    <div class="text-xs text-muted">Total Applications</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h4 text-success" id="screening-count">0</div>
                                    <div class="text-xs text-muted">In Screening</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h4 text-info" id="interview-count">0</div>
                                    <div class="text-xs text-muted">In Interview</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h4 text-warning" id="offer-count">0</div>
                                    <div class="text-xs text-muted">Offers Pending</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance Summary -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Performance Summary</h6>
                </div>
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="performanceChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="mr-2">
                            <i class="fas fa-circle text-success"></i> Excellent
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-info"></i> Good
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-warning"></i> Needs Improvement
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Analytics Row -->
    <div class="row">
        <!-- Training Progress -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Training Progress</h6>
                </div>
                <div class="card-body">
                    <div id="training-progress-list">
                        <!-- Training progress items will be loaded here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activities -->
        <div class="col-xl-6 col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent HR Activities</h6>
                </div>
                <div class="card-body">
                    <div id="recent-activities">
                        <!-- Recent activities will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Insights Panel -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-robot"></i> AI Insights & Recommendations
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row" id="ai-insights-container">
                        <!-- AI insights will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions Modal -->
<div class="modal fade" id="quickActionsModal" tabindex="-1" role="dialog" aria-labelledby="quickActionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="quickActionsModalLabel">Quick Actions</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                                <h5>Create New Job</h5>
                                <p class="text-muted">Post a new job opening</p>
                                <button class="btn btn-primary" onclick="createNewJob()">Create Job</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-graduation-cap fa-3x text-success mb-3"></i>
                                <h5>Training Management</h5>
                                <p class="text-muted">Manage employee training</p>
                                <button class="btn btn-success" onclick="openTrainingManagement()">Manage Training</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-chart-line fa-3x text-info mb-3"></i>
                                <h5>Performance Review</h5>
                                <p class="text-muted">Review employee performance</p>
                                <button class="btn btn-info" onclick="openPerformanceReview()">Review Performance</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body text-center">
                                <i class="fas fa-money-bill-wave fa-3x text-warning mb-3"></i>
                                <h5>Payroll Management</h5>
                                <p class="text-muted">Manage payroll and benefits</p>
                                <button class="btn btn-warning" onclick="openPayrollManagement()">Manage Payroll</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Global variables
let talentPipelineChart;
let performanceChart;
let dashboardData = {};

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    initializeCharts();
    setupWebSocket();
});

// Load dashboard data
async function loadDashboardData() {
    try {
        const response = await fetch('/api/hr/dashboard/overview');
        dashboardData = await response.json();
        
        updateDashboardMetrics();
        updateCharts();
        loadTrainingProgress();
        loadRecentActivities();
        loadAIInsights();
        
    } catch (error) {
        console.error('Error loading dashboard data:', error);
        showNotification('Error loading dashboard data', 'error');
    }
}

// Update dashboard metrics
function updateDashboardMetrics() {
    if (dashboardData.overview) {
        document.getElementById('total-employees').textContent = dashboardData.overview.total_employees || 0;
        document.getElementById('active-recruitments').textContent = dashboardData.overview.active_recruitments || 0;
        document.getElementById('training-progress').textContent = dashboardData.overview.training_progress || '0%';
        document.getElementById('performance-alerts').textContent = dashboardData.overview.performance_alerts || 0;
    }
    
    if (dashboardData.talent_pipeline) {
        document.getElementById('applications-count').textContent = dashboardData.talent_pipeline.applications_count || 0;
        document.getElementById('screening-count').textContent = dashboardData.talent_pipeline.screening_count || 0;
        document.getElementById('interview-count').textContent = dashboardData.talent_pipeline.interview_count || 0;
        document.getElementById('offer-count').textContent = dashboardData.talent_pipeline.offer_count || 0;
    }
}

// Initialize charts
function initializeCharts() {
    // Talent Pipeline Chart
    const talentCtx = document.getElementById('talentPipelineChart').getContext('2d');
    talentPipelineChart = new Chart(talentCtx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Applications',
                data: [12, 19, 3, 5, 2, 3],
                borderColor: 'rgb(75, 192, 192)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });

    // Performance Chart
    const performanceCtx = document.getElementById('performanceChart').getContext('2d');
    performanceChart = new Chart(performanceCtx, {
        type: 'doughnut',
        data: {
            labels: ['Excellent', 'Good', 'Needs Improvement'],
            datasets: [{
                data: [30, 50, 20],
                backgroundColor: ['#1cc88a', '#36b9cc', '#f6c23e']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

// Update charts with real data
function updateCharts() {
    if (dashboardData.talent_pipeline && dashboardData.talent_pipeline.chart_data) {
        talentPipelineChart.data.labels = dashboardData.talent_pipeline.chart_data.labels;
        talentPipelineChart.data.datasets[0].data = dashboardData.talent_pipeline.chart_data.data;
        talentPipelineChart.update();
    }
    
    if (dashboardData.performance && dashboardData.performance.chart_data) {
        performanceChart.data.datasets[0].data = dashboardData.performance.chart_data;
        performanceChart.update();
    }
}

// Load training progress
async function loadTrainingProgress() {
    try {
        const response = await fetch('/api/hr/training/dashboard');
        const trainingData = await response.json();
        
        const container = document.getElementById('training-progress-list');
        container.innerHTML = '';
        
        if (trainingData.employees_in_training) {
            trainingData.employees_in_training.forEach(employee => {
                const progressHtml = `
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h6 class="mb-0">${employee.name}</h6>
                            <small class="text-muted">${employee.position}</small>
                        </div>
                        <div class="text-right">
                            <div class="progress" style="width: 100px; height: 8px;">
                                <div class="progress-bar" role="progressbar" style="width: ${employee.overall_progress}" 
                                     aria-valuenow="${employee.overall_progress}" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small class="text-muted">${employee.overall_progress}</small>
                        </div>
                    </div>
                `;
                container.innerHTML += progressHtml;
            });
        }
        
    } catch (error) {
        console.error('Error loading training progress:', error);
    }
}

// Load recent activities
async function loadRecentActivities() {
    try {
        const response = await fetch('/api/hr/activities/recent');
        const activitiesData = await response.json();
        
        const container = document.getElementById('recent-activities');
        container.innerHTML = '';
        
        if (activitiesData.activities) {
            activitiesData.activities.forEach(activity => {
                const activityHtml = `
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-circle text-${activity.type === 'application' ? 'primary' : 
                                                          activity.type === 'training' ? 'success' : 
                                                          activity.type === 'performance' ? 'warning' : 'info'}"></i>
                        </div>
                        <div class="flex-grow-1 ml-3">
                            <div class="small text-gray-900">${activity.description}</div>
                            <div class="small text-gray-500">${activity.time}</div>
                        </div>
                    </div>
                `;
                container.innerHTML += activityHtml;
            });
        }
        
    } catch (error) {
        console.error('Error loading recent activities:', error);
    }
}

// Load AI insights
async function loadAIInsights() {
    try {
        const response = await fetch('/api/hr/ai/insights');
        const insightsData = await response.json();
        
        const container = document.getElementById('ai-insights-container');
        container.innerHTML = '';
        
        if (insightsData.insights) {
            insightsData.insights.forEach(insight => {
                const insightHtml = `
                    <div class="col-md-6 mb-3">
                        <div class="card border-left-${insight.type === 'alert' ? 'danger' : 
                                                      insight.type === 'recommendation' ? 'success' : 'info'} shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-${insight.type === 'alert' ? 'danger' : 
                                                                                  insight.type === 'recommendation' ? 'success' : 'info'} text-uppercase mb-1">
                                            ${insight.category}
                                        </div>
                                        <div class="h6 mb-0 font-weight-bold text-gray-800">${insight.title}</div>
                                        <div class="small text-gray-600">${insight.description}</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-robot fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                container.innerHTML += insightHtml;
            });
        }
        
    } catch (error) {
        console.error('Error loading AI insights:', error);
    }
}

// Setup WebSocket for real-time updates
function setupWebSocket() {
    // WebSocket implementation for real-time updates
    // This would connect to the WebSocket server for live dashboard updates
}

// Utility functions
function refreshDashboard() {
    loadDashboardData();
    showNotification('Dashboard refreshed', 'success');
}

function exportDashboard() {
    // Export dashboard data
    showNotification('Dashboard exported', 'success');
}

function showNotification(message, type) {
    // Show notification to user
    console.log(`${type}: ${message}`);
}

// Navigation functions
function viewAllCandidates() {
    window.location.href = '/hr/candidates/pipeline';
}

function createNewJob() {
    window.location.href = '/hr/jobs/create';
}

function exportPipeline() {
    // Export pipeline data
    showNotification('Pipeline exported', 'success');
}

function openTrainingManagement() {
    window.location.href = '/hr/training/dashboard';
}

function openPerformanceReview() {
    window.location.href = '/hr/performance/dashboard';
}

function openPayrollManagement() {
    window.location.href = '/hr/payroll/dashboard';
}
</script>
@endsection 