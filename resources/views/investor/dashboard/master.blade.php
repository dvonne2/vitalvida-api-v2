@extends('layouts.app')

@section('title', 'Master Readiness Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Master Readiness Dashboard</h1>
                    <p class="text-muted mb-0">Document readiness overview & investor preparation</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="refreshDashboard()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-primary" onclick="exportDocuments()">
                        <i class="fas fa-download"></i> Export Documents
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Readiness Overview -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-clipboard-check text-success"></i>
                        Document Readiness Overview
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <canvas id="documentReadinessChart" height="200"></canvas>
                        </div>
                        <div class="col-md-6">
                            <div class="document-stats">
                                <div class="stat-item">
                                    <div class="stat-number text-success">28</div>
                                    <div class="stat-label">Documents Ready</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number text-warning">2</div>
                                    <div class="stat-label">In Progress</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number text-danger">0</div>
                                    <div class="stat-label">Missing</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number text-primary">93.3%</div>
                                    <div class="stat-label">Completion Rate</div>
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
                        <i class="fas fa-clock text-info"></i>
                        Recent Updates
                    </h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">P&L Statement Updated</div>
                                <div class="timeline-time">2 hours ago</div>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-warning"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">Cash Flow Statement</div>
                                <div class="timeline-time">4 hours ago</div>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <div class="timeline-title">Balance Sheet Completed</div>
                                <div class="timeline-time">1 day ago</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Categories -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-folder-open text-primary"></i>
                        Document Categories
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <div class="category-card bg-success text-white">
                                <div class="category-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="category-content">
                                    <h6>Financials</h6>
                                    <div class="progress bg-white bg-opacity-25">
                                        <div class="progress-bar bg-white" style="width: 100%"></div>
                                    </div>
                                    <small>8/8 Complete</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="category-card bg-primary text-white">
                                <div class="category-icon">
                                    <i class="fas fa-cogs"></i>
                                </div>
                                <div class="category-content">
                                    <h6>Operations</h6>
                                    <div class="progress bg-white bg-opacity-25">
                                        <div class="progress-bar bg-white" style="width: 100%"></div>
                                    </div>
                                    <small>6/6 Complete</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="category-card bg-warning text-white">
                                <div class="category-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="category-content">
                                    <h6>Governance</h6>
                                    <div class="progress bg-white bg-opacity-25">
                                        <div class="progress-bar bg-white" style="width: 90%"></div>
                                    </div>
                                    <small>9/10 Complete</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="category-card bg-info text-white">
                                <div class="category-icon">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <div class="category-content">
                                    <h6>Vision</h6>
                                    <div class="progress bg-white bg-opacity-25">
                                        <div class="progress-bar bg-white" style="width: 100%"></div>
                                    </div>
                                    <small>6/6 Complete</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Document List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list text-secondary"></i>
                        Document List
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Document</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file-pdf text-danger me-2"></i>
                                            <div>
                                                <div class="fw-bold">P&L Statement (Last 12 Months)</div>
                                                <small class="text-muted">Financials</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-success">Financials</span></td>
                                    <td><span class="badge bg-success">Complete</span></td>
                                    <td>2 hours ago</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDocument(1)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="downloadDocument(1)">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file-pdf text-danger me-2"></i>
                                            <div>
                                                <div class="fw-bold">Balance Sheet</div>
                                                <small class="text-muted">Financials</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-success">Financials</span></td>
                                    <td><span class="badge bg-success">Complete</span></td>
                                    <td>1 day ago</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDocument(2)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="downloadDocument(2)">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-file-pdf text-danger me-2"></i>
                                            <div>
                                                <div class="fw-bold">Cash Flow Statement</div>
                                                <small class="text-muted">Financials</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-success">Financials</span></td>
                                    <td><span class="badge bg-warning">In Progress</span></td>
                                    <td>4 hours ago</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="viewDocument(3)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="downloadDocument(3)">
                                            <i class="fas fa-download"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
        // Subscribe to investor dashboard channel
        wsConnection.send(JSON.stringify({
            type: 'subscribe',
            channel: 'investor-dashboard-master_readiness'
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
        case 'document_status_changed':
            updateDocumentStatus(data.document_id, data.status);
            break;
        case 'document_uploaded':
            addNewDocument(data.document);
            break;
        case 'completion_percentage_updated':
            updateCompletionPercentage(data.percentage);
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
    const ctx = document.getElementById('documentReadinessChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Complete', 'In Progress', 'Missing'],
            datasets: [{
                data: [28, 2, 0],
                backgroundColor: ['#28a745', '#ffc107', '#dc3545'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// Real-time updates
function startRealTimeUpdates() {
    setInterval(function() {
        fetch('/api/investor/websocket/document-updates')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateDocumentStats(data.data);
                }
            })
            .catch(error => console.error('Error fetching updates:', error));
    }, 30000); // Update every 30 seconds
}

// Update document statistics
function updateDocumentStats(data) {
    // Update completion percentage
    const completionElement = document.querySelector('.stat-number.text-primary');
    if (completionElement) {
        completionElement.textContent = data.completion_percentage + '%';
    }
    
    // Update recent updates
    updateTimeline(data.recent_updates);
}

// Refresh dashboard
function refreshDashboard() {
    location.reload();
}

// Export documents
function exportDocuments() {
    fetch('/api/investor/export/full-package', {
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
    .catch(error => console.error('Error exporting documents:', error));
}

// View document
function viewDocument(documentId) {
    window.open(`/api/investor/documents/${documentId}`, '_blank');
}

// Download document
function downloadDocument(documentId) {
    window.open(`/api/investor/documents/${documentId}/download`, '_blank');
}
</script>

<style>
.category-card {
    padding: 1rem;
    border-radius: 0.5rem;
    height: 120px;
    display: flex;
    align-items: center;
}

.category-icon {
    font-size: 2rem;
    margin-right: 1rem;
}

.category-content h6 {
    margin-bottom: 0.5rem;
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

@media (max-width: 768px) {
    .category-card {
        height: auto;
        margin-bottom: 1rem;
    }
    
    .stat-item {
        margin-bottom: 0.5rem;
    }
}
</style>
@endsection 