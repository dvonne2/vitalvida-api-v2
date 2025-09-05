@extends('layouts.app')

@section('title', 'Tomi Governance Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Tomi Governance Dashboard</h1>
                    <p class="text-muted mb-0">Financial oversight & compliance tracking</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-primary" onclick="exportComplianceReport()">
                        <i class="fas fa-file-pdf"></i> Export Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Oversight Overview -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-line text-primary"></i>
                        Financial Oversight Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="financialOverviewChart" height="200"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div class="financial-stats">
                                <div class="stat-item">
                                    <div class="stat-number text-success">₦2,495,000</div>
                                    <div class="stat-label">Cash Position</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number text-warning">₦500,000</div>
                                    <div class="stat-label">Monthly Burn Rate</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number text-info">150</div>
                                    <div class="stat-label">Runway Days</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number text-success">95%</div>
                                    <div class="stat-label">Compliance Score</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-shield-alt text-success"></i>
                        Compliance Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="compliance-items">
                        <div class="compliance-item">
                            <div class="compliance-icon bg-success">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="compliance-content">
                                <div class="compliance-title">GDPR Compliance</div>
                                <div class="compliance-status">Active</div>
                            </div>
                        </div>
                        <div class="compliance-item">
                            <div class="compliance-icon bg-success">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="compliance-content">
                                <div class="compliance-title">Financial Controls</div>
                                <div class="compliance-status">Implemented</div>
                            </div>
                        </div>
                        <div class="compliance-item">
                            <div class="compliance-icon bg-warning">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="compliance-content">
                                <div class="compliance-title">SOX Compliance</div>
                                <div class="compliance-status">In Progress</div>
                            </div>
                        </div>
                        <div class="compliance-item">
                            <div class="compliance-icon bg-success">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="compliance-content">
                                <div class="compliance-title">Audit Trail</div>
                                <div class="compliance-status">Complete</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Governance Metrics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-balance-scale text-info"></i>
                        Governance Metrics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="metric-card bg-primary text-white">
                                <div class="metric-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="metric-content">
                                    <h6>Board Meetings</h6>
                                    <div class="metric-value">4</div>
                                    <small>This Quarter</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="metric-card bg-success text-white">
                                <div class="metric-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="metric-content">
                                    <h6>Compliance Reports</h6>
                                    <div class="metric-value">12</div>
                                    <small>This Year</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="metric-card bg-info text-white">
                                <div class="metric-icon">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="metric-content">
                                    <h6>Audit Status</h6>
                                    <div class="metric-value">Clean</div>
                                    <small>Last Audit</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="metric-card bg-warning text-white">
                                <div class="metric-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="metric-content">
                                    <h6>Risk Score</h6>
                                    <div class="metric-value">Low</div>
                                    <small>Current Level</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Financial Controls -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-lock text-danger"></i>
                        Financial Controls
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Control</th>
                                    <th>Status</th>
                                    <th>Last Review</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Cash Management</td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td>2 days ago</td>
                                </tr>
                                <tr>
                                    <td>Expense Approval</td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td>1 week ago</td>
                                </tr>
                                <tr>
                                    <td>Budget Controls</td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td>3 days ago</td>
                                </tr>
                                <tr>
                                    <td>Financial Reporting</td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td>1 day ago</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history text-secondary"></i>
                        Recent Governance Activities
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">Board Meeting Completed</div>
                                <div class="timeline-time">2 days ago</div>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-info"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">Compliance Report Generated</div>
                                <div class="timeline-time">1 week ago</div>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-warning"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">Risk Assessment Updated</div>
                                <div class="timeline-time">2 weeks ago</div>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">Audit Trail Verified</div>
                                <div class="timeline-time">3 weeks ago</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- WebSocket Connection -->
<script>
let wsConnection = null;

// Initialize WebSocket connection
function initializeWebSocket() {
    const wsUrl = '{{ config("app.websocket_url", "ws://localhost:6001") }}';
    wsConnection = new WebSocket(wsUrl);
    
    wsConnection.onopen = function() {
        console.log('WebSocket connected');
        // Subscribe to governance dashboard channel
        wsConnection.send(JSON.stringify({
            type: 'subscribe',
            channel: 'investor-dashboard-tomi_governance'
        }));
    };
    
    wsConnection.onmessage = function(event) {
        const data = JSON.parse(event.data);
        handleWebSocketMessage(data);
    };
    
    wsConnection.onclose = function() {
        console.log('WebSocket disconnected');
        // Reconnect after 5 seconds
        setTimeout(initializeWebSocket, 5000);
    };
}

// Handle WebSocket messages
function handleWebSocketMessage(data) {
    switch(data.type) {
        case 'cash_position_updated':
            updateCashPosition(data.cash_position);
            break;
        case 'compliance_score_updated':
            updateComplianceScore(data.score);
            break;
        case 'financial_metrics_updated':
            updateFinancialMetrics(data.metrics);
            break;
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeWebSocket();
    startRealTimeUpdates();
});

// Initialize charts
function initializeCharts() {
    const ctx = document.getElementById('financialOverviewChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            datasets: [{
                label: 'Cash Position',
                data: [1800000, 2000000, 2200000, 2400000, 2450000, 2495000],
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '₦' + (value / 1000000).toFixed(1) + 'M';
                        }
                    }
                }
            }
        }
    });
}

// Real-time updates
function startRealTimeUpdates() {
    setInterval(function() {
        fetch('/api/investor/websocket/financial-stream')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateFinancialData(data.data);
                }
            })
            .catch(error => console.error('Error fetching updates:', error));
    }, 30000); // Update every 30 seconds
}

// Update financial data
function updateFinancialData(data) {
    // Update cash position
    const cashPositionElement = document.querySelector('.stat-number.text-success');
    if (cashPositionElement && data.metrics.cash_position) {
        cashPositionElement.textContent = '₦' + (data.metrics.cash_position / 1000000).toFixed(1) + 'M';
    }
    
    // Update runway days
    const runwayElement = document.querySelector('.stat-number.text-info');
    if (runwayElement && data.metrics.runway_days) {
        runwayElement.textContent = data.metrics.runway_days;
    }
}

// Refresh dashboard
function refreshDashboard() {
    location.reload();
}

// Export compliance report
function exportComplianceReport() {
    fetch('/api/investor/export/financial-package', {
        method: 'GET',
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Create download link
            const link = document.createElement('a');
            link.href = data.data.download_url;
            link.download = data.data.file_name;
            link.click();
        }
    })
    .catch(error => console.error('Error exporting report:', error));
}
</script>

<style>
.metric-card {
    padding: 1rem;
    border-radius: 0.5rem;
    height: 120px;
    display: flex;
    align-items: center;
}

.metric-icon {
    font-size: 2rem;
    margin-right: 1rem;
}

.metric-content h6 {
    margin-bottom: 0.5rem;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.compliance-item {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.compliance-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 1rem;
}

.compliance-content {
    flex: 1;
}

.compliance-title {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.compliance-status {
    font-size: 0.875rem;
    color: #6c757d;
}

.stat-item {
    text-align: center;
    margin-bottom: 1rem;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.875rem;
    color: #6c757d;
}

.timeline {
    position: relative;
}

.timeline-item {
    display: flex;
    align-items: center;
    margin-bottom: 1rem;
}

.timeline-marker {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 1rem;
}

.timeline-content {
    flex: 1;
}

.timeline-title {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.timeline-time {
    font-size: 0.875rem;
    color: #6c757d;
}

@media (max-width: 768px) {
    .metric-card {
        height: auto;
        margin-bottom: 1rem;
    }
    
    .stat-item {
        margin-bottom: 0.5rem;
    }
}
</style>
@endsection 