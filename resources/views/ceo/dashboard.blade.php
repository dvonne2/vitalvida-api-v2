<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEO Command Center - VitalVida</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .alert-card {
            background: #fff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #dc3545;
        }
        .status-good { border-left-color: #28a745; }
        .status-monitor { border-left-color: #ffc107; }
        .status-fix { border-left-color: #dc3545; }
        .real-time-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            padding: 10px 15px;
            border-radius: 25px;
            z-index: 1000;
        }
        .metric-value {
            font-size: 2.5rem;
            font-weight: bold;
        }
        .metric-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .trend-up { color: #28a745; }
        .trend-down { color: #dc3545; }
        .trend-stable { color: #ffc107; }
    </style>
</head>
<body class="bg-light">
    <div class="real-time-indicator">
        <i class="fas fa-circle"></i> Live Updates
    </div>

    <div class="container-fluid">
        <div class="row">
            <!-- Header -->
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center py-3">
                    <h1><i class="fas fa-tachometer-alt"></i> CEO Command Center</h1>
                    <div class="text-muted">Last Updated: <span id="last-updated">Loading...</span></div>
                </div>
            </div>
        </div>

        <!-- Main Metrics Row -->
        <div class="row">
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value" id="monthly-orders">-</div>
                    <div class="metric-label">Monthly Orders</div>
                    <div class="mt-2">
                        <span id="order-growth" class="trend-up">+23% vs last month</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value" id="revenue">-</div>
                    <div class="metric-label">Revenue</div>
                    <div class="mt-2">
                        <span id="revenue-growth" class="trend-up">18% net margin</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value" id="active-das">-</div>
                    <div class="metric-label">Active DAs</div>
                    <div class="mt-2">
                        <span id="da-status">Across 9 states</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="metric-card">
                    <div class="metric-value" id="cash-position">-</div>
                    <div class="metric-label">Cash Position</div>
                    <div class="mt-2">
                        <span id="cash-sources">GTB + Moniepoint</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Revenue Trend</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="revenueChart" height="100"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-pie"></i> Department Performance</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="departmentChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts and Order Flow -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle"></i> Where is the fire?</h5>
                    </div>
                    <div class="card-body" id="alerts-container">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-shipping-fast"></i> Order Flow</h5>
                    </div>
                    <div class="card-body" id="order-flow-container">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Roadmap and Experiments -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-road"></i> Quarterly Roadmap</h5>
                    </div>
                    <div class="card-body" id="roadmap-container">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-flask"></i> Experiment Lab</h5>
                    </div>
                    <div class="card-body" id="experiments-container">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // WebSocket connection
        let ws = null;
        let reconnectAttempts = 0;
        const maxReconnectAttempts = 5;

        function connectWebSocket() {
            try {
                ws = new WebSocket('ws://localhost:6001');
                
                ws.onopen = function() {
                    console.log('WebSocket connected');
                    reconnectAttempts = 0;
                    document.querySelector('.real-time-indicator').style.background = '#28a745';
                };

                ws.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    updateDashboard(data);
                };

                ws.onclose = function() {
                    console.log('WebSocket disconnected');
                    document.querySelector('.real-time-indicator').style.background = '#dc3545';
                    
                    if (reconnectAttempts < maxReconnectAttempts) {
                        setTimeout(() => {
                            reconnectAttempts++;
                            connectWebSocket();
                        }, 5000);
                    }
                };

                ws.onerror = function(error) {
                    console.error('WebSocket error:', error);
                };
            } catch (error) {
                console.error('WebSocket connection failed:', error);
            }
        }

        // Initialize dashboard
        function initDashboard() {
            loadDashboardData();
            loadAlerts();
            loadOrderFlow();
            loadRoadmap();
            loadExperiments();
            initCharts();
            
            // Refresh data every 30 seconds
            setInterval(loadDashboardData, 30000);
        }

        // Load dashboard data
        async function loadDashboardData() {
            try {
                const response = await fetch('/api/ceo/dashboard');
                const data = await response.json();
                
                if (data.success) {
                    updateMetrics(data.data);
                    updateLastUpdated();
                }
            } catch (error) {
                console.error('Failed to load dashboard data:', error);
            }
        }

        // Load alerts
        async function loadAlerts() {
            try {
                const response = await fetch('/api/ceo/alerts');
                const data = await response.json();
                
                if (data.success) {
                    updateAlerts(data.data);
                }
            } catch (error) {
                console.error('Failed to load alerts:', error);
            }
        }

        // Load order flow
        async function loadOrderFlow() {
            try {
                const response = await fetch('/api/ceo/order-flow');
                const data = await response.json();
                
                if (data.success) {
                    updateOrderFlow(data.data);
                }
            } catch (error) {
                console.error('Failed to load order flow:', error);
            }
        }

        // Load roadmap
        async function loadRoadmap() {
            try {
                const response = await fetch('/api/ceo/analytics/roadmap');
                const data = await response.json();
                
                if (data.success) {
                    updateRoadmap(data.data);
                }
            } catch (error) {
                console.error('Failed to load roadmap:', error);
            }
        }

        // Load experiments
        async function loadExperiments() {
            try {
                const response = await fetch('/api/ceo/analytics/experiments');
                const data = await response.json();
                
                if (data.success) {
                    updateExperiments(data.data);
                }
            } catch (error) {
                console.error('Failed to load experiments:', error);
            }
        }

        // Update metrics
        function updateMetrics(data) {
            document.getElementById('monthly-orders').textContent = data.monthly_orders.count.toLocaleString();
            document.getElementById('order-growth').textContent = data.monthly_orders.growth_percentage;
            document.getElementById('order-growth').className = data.monthly_orders.trend === 'up' ? 'trend-up' : 'trend-down';
            
            document.getElementById('revenue').textContent = '₦' + (data.revenue.amount / 1000000).toFixed(1) + 'M';
            document.getElementById('revenue-growth').textContent = data.revenue.growth_percentage;
            
            document.getElementById('active-das').textContent = data.active_das.count;
            document.getElementById('da-status').textContent = data.active_das.status;
        }

        // Update alerts
        function updateAlerts(alerts) {
            const container = document.getElementById('alerts-container');
            container.innerHTML = '';
            
            alerts.forEach(alert => {
                const alertHtml = `
                    <div class="alert-card status-${alert.severity}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${alert.title}</strong>
                                <div class="text-muted small">${alert.department} • ${alert.action}</div>
                            </div>
                            <span class="badge bg-${alert.severity === 'high' ? 'danger' : alert.severity === 'medium' ? 'warning' : 'success'}">${alert.severity}</span>
                        </div>
                    </div>
                `;
                container.innerHTML += alertHtml;
            });
        }

        // Update order flow
        function updateOrderFlow(data) {
            const container = document.getElementById('order-flow-container');
            container.innerHTML = `
                <div class="row">
                    <div class="col-6">
                        <div class="text-center">
                            <div class="h4">${data.leads_called.percentage}%</div>
                            <div class="text-muted">Leads Called</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="h4">${data.packages_sealed.percentage}%</div>
                            <div class="text-muted">Packages Sealed</div>
                        </div>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-4">
                        <div class="text-center">
                            <div class="h5">${data.daily_metrics.orders_created_today}</div>
                            <div class="text-muted small">Created Today</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center">
                            <div class="h5">${data.daily_metrics.out_for_delivery}</div>
                            <div class="text-muted small">Out for Delivery</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center">
                            <div class="h5">${data.daily_metrics.delivered_today}</div>
                            <div class="text-muted small">Delivered Today</div>
                        </div>
                    </div>
                </div>
            `;
        }

        // Update roadmap
        function updateRoadmap(roadmap) {
            const container = document.getElementById('roadmap-container');
            container.innerHTML = '';
            
            roadmap.forEach(initiative => {
                const progressClass = initiative.completion >= 80 ? 'success' : 
                    initiative.completion >= 60 ? 'warning' : 'danger';
                
                const roadmapHtml = `
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong>${initiative.initiative}</strong>
                            <span class="badge bg-${progressClass}">${initiative.completion}%</span>
                        </div>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-${progressClass}" style="width: ${initiative.completion}%"></div>
                        </div>
                        <div class="text-muted small">${initiative.owner} • ${initiative.quarter}</div>
                    </div>
                `;
                container.innerHTML += roadmapHtml;
            });
        }

        // Update experiments
        function updateExperiments(experiments) {
            const container = document.getElementById('experiments-container');
            container.innerHTML = '';
            
            experiments.forEach(experiment => {
                const statusClass = experiment.status === 'active' ? 'success' : 
                    experiment.verdict === 'keep' ? 'info' : 'danger';
                
                const experimentHtml = `
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${experiment.idea}</strong>
                                <div class="text-muted small">${experiment.channel} • ${experiment.owner}</div>
                            </div>
                            <span class="badge bg-${statusClass}">${experiment.status}</span>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">ROI: ${experiment.roi}% • Budget: ₦${experiment.budget_allocated.toLocaleString()}</small>
                        </div>
                    </div>
                `;
                container.innerHTML += experimentHtml;
            });
        }

        // Initialize charts
        function initCharts() {
            // Revenue Chart
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                    datasets: [{
                        label: 'Revenue (₦M)',
                        data: [15.8, 18.2, 16.5, 19.1, 20.3, 22.1],
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });

            // Department Chart
            const departmentCtx = document.getElementById('departmentChart').getContext('2d');
            new Chart(departmentCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Sales', 'Media', 'Inventory', 'Logistics', 'Finance', 'Customer Service'],
                    datasets: [{
                        data: [25, 20, 15, 18, 12, 10],
                        backgroundColor: [
                            '#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe', '#00f2fe'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        }

        // Update last updated timestamp
        function updateLastUpdated() {
            document.getElementById('last-updated').textContent = new Date().toLocaleTimeString();
        }

        // Update dashboard from WebSocket
        function updateDashboard(data) {
            if (data.type === 'dashboard_update') {
                updateMetrics(data.data);
                updateLastUpdated();
            } else if (data.type === 'alert_new') {
                loadAlerts();
            } else if (data.type === 'order_update') {
                loadOrderFlow();
            }
        }

        // Initialize when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initDashboard();
            connectWebSocket();
        });
    </script>
</body>
</html> 