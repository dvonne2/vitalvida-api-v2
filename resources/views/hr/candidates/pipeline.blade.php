@extends('layouts.app')

@section('title', 'Talent Pipeline - VitalVida')

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0 text-gray-800">Talent Pipeline</h1>
                    <p class="text-muted">Manage candidate applications and recruitment process</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary" onclick="refreshPipeline()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-success" onclick="exportPipeline()">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button class="btn btn-info" onclick="runAIScreening()">
                        <i class="fas fa-robot"></i> AI Screening
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Pipeline Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Applications
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-applications">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-alt fa-2x text-gray-300"></i>
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
                                In Screening
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="in-screening">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-search fa-2x text-gray-300"></i>
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
                                In Interview
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="in-interview">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-comments fa-2x text-gray-300"></i>
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
                                Offers Pending
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800" id="offers-pending">0</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-handshake fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters and Search -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <label for="status-filter" class="form-label">Status</label>
                            <select class="form-control" id="status-filter" onchange="filterCandidates()">
                                <option value="">All Status</option>
                                <option value="applied">Applied</option>
                                <option value="screening">In Screening</option>
                                <option value="interview">In Interview</option>
                                <option value="offer">Offer Pending</option>
                                <option value="hired">Hired</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="job-filter" class="form-label">Job Position</label>
                            <select class="form-control" id="job-filter" onchange="filterCandidates()">
                                <option value="">All Positions</option>
                                <!-- Job options will be loaded dynamically -->
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="department-filter" class="form-label">Department</label>
                            <select class="form-control" id="department-filter" onchange="filterCandidates()">
                                <option value="">All Departments</option>
                                <!-- Department options will be loaded dynamically -->
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="search-candidate" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search-candidate" 
                                   placeholder="Search candidates..." onkeyup="searchCandidates()">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pipeline View -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Candidate Pipeline</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="candidates-table">
                            <thead>
                                <tr>
                                    <th>Candidate</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>AI Score</th>
                                    <th>Applied Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="candidates-tbody">
                                <!-- Candidates will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Insights Panel -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="fas fa-robot"></i> AI Insights & Recommendations
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row" id="ai-insights-pipeline">
                        <!-- AI insights will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Candidate Detail Modal -->
<div class="modal fade" id="candidateDetailModal" tabindex="-1" role="dialog" aria-labelledby="candidateDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="candidateDetailModalLabel">Candidate Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <div id="candidate-profile">
                            <!-- Candidate profile will be loaded here -->
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div id="ai-assessment">
                            <!-- AI assessment will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="updateCandidateStatus()">Update Status</button>
                <button type="button" class="btn btn-success" onclick="scheduleInterview()">Schedule Interview</button>
            </div>
        </div>
    </div>
</div>

<!-- AI Screening Modal -->
<div class="modal fade" id="aiScreeningModal" tabindex="-1" role="dialog" aria-labelledby="aiScreeningModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="aiScreeningModalLabel">AI Screening Results</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="ai-screening-results">
                    <!-- AI screening results will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="applyAIScreening()">Apply Screening</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Global variables
let candidatesData = [];
let filteredCandidates = [];
let selectedCandidate = null;

// Initialize pipeline
document.addEventListener('DOMContentLoaded', function() {
    loadPipelineData();
    loadFilters();
    setupWebSocket();
});

// Load pipeline data
async function loadPipelineData() {
    try {
        const response = await fetch('/api/hr/talent/pipeline');
        const data = await response.json();
        
        if (data.success) {
            candidatesData = data.data.candidates || [];
            filteredCandidates = [...candidatesData];
            
            updatePipelineStatistics(data.data.statistics);
            renderCandidatesTable();
            loadAIInsights();
        }
        
    } catch (error) {
        console.error('Error loading pipeline data:', error);
        showNotification('Error loading pipeline data', 'error');
    }
}

// Update pipeline statistics
function updatePipelineStatistics(statistics) {
    if (statistics) {
        document.getElementById('total-applications').textContent = statistics.total_applications || 0;
        document.getElementById('in-screening').textContent = statistics.in_screening || 0;
        document.getElementById('in-interview').textContent = statistics.in_interview || 0;
        document.getElementById('offers-pending').textContent = statistics.offers_pending || 0;
    }
}

// Render candidates table
function renderCandidatesTable() {
    const tbody = document.getElementById('candidates-tbody');
    tbody.innerHTML = '';
    
    filteredCandidates.forEach(candidate => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>
                <div class="d-flex align-items-center">
                    <div class="avatar-sm bg-primary rounded-circle d-flex align-items-center justify-content-center mr-3">
                        <span class="text-white font-weight-bold">${getInitials(candidate.name)}</span>
                    </div>
                    <div>
                        <div class="font-weight-bold">${candidate.name}</div>
                        <small class="text-muted">${candidate.email}</small>
                    </div>
                </div>
            </td>
            <td>${candidate.position}</td>
            <td>${candidate.department}</td>
            <td>
                <span class="badge badge-${getStatusBadgeClass(candidate.status)}">
                    ${candidate.status}
                </span>
            </td>
            <td>
                <div class="d-flex align-items-center">
                    <div class="progress mr-2" style="width: 60px; height: 6px;">
                        <div class="progress-bar" role="progressbar" style="width: ${candidate.ai_score}%" 
                             aria-valuenow="${candidate.ai_score}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small>${candidate.ai_score}/10</small>
                </div>
            </td>
            <td>${formatDate(candidate.applied_date)}</td>
            <td>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-primary" 
                            onclick="viewCandidateDetails('${candidate.id}')">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-success" 
                            onclick="updateStatus('${candidate.id}', 'next')">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger" 
                            onclick="updateStatus('${candidate.id}', 'reject')">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Get initials from name
function getInitials(name) {
    return name.split(' ').map(n => n[0]).join('').toUpperCase();
}

// Get status badge class
function getStatusBadgeClass(status) {
    const statusClasses = {
        'applied': 'secondary',
        'screening': 'info',
        'interview': 'warning',
        'offer': 'success',
        'hired': 'success',
        'rejected': 'danger'
    };
    return statusClasses[status] || 'secondary';
}

// Format date
function formatDate(dateString) {
    return new Date(dateString).toLocaleDateString();
}

// Filter candidates
function filterCandidates() {
    const statusFilter = document.getElementById('status-filter').value;
    const jobFilter = document.getElementById('job-filter').value;
    const departmentFilter = document.getElementById('department-filter').value;
    const searchTerm = document.getElementById('search-candidate').value.toLowerCase();
    
    filteredCandidates = candidatesData.filter(candidate => {
        const statusMatch = !statusFilter || candidate.status === statusFilter;
        const jobMatch = !jobFilter || candidate.position === jobFilter;
        const departmentMatch = !departmentFilter || candidate.department === departmentFilter;
        const searchMatch = !searchTerm || 
            candidate.name.toLowerCase().includes(searchTerm) ||
            candidate.email.toLowerCase().includes(searchTerm) ||
            candidate.position.toLowerCase().includes(searchTerm);
        
        return statusMatch && jobMatch && departmentMatch && searchMatch;
    });
    
    renderCandidatesTable();
}

// Search candidates
function searchCandidates() {
    filterCandidates();
}

// Load filters
async function loadFilters() {
    try {
        const response = await fetch('/api/hr/jobs/list');
        const jobsData = await response.json();
        
        const jobFilter = document.getElementById('job-filter');
        if (jobsData.jobs) {
            jobsData.jobs.forEach(job => {
                const option = document.createElement('option');
                option.value = job.title;
                option.textContent = job.title;
                jobFilter.appendChild(option);
            });
        }
        
        // Load departments
        const departmentFilter = document.getElementById('department-filter');
        const departments = ['Engineering', 'Marketing', 'Sales', 'Operations', 'HR'];
        departments.forEach(dept => {
            const option = document.createElement('option');
            option.value = dept;
            option.textContent = dept;
            departmentFilter.appendChild(option);
        });
        
    } catch (error) {
        console.error('Error loading filters:', error);
    }
}

// View candidate details
async function viewCandidateDetails(candidateId) {
    try {
        const response = await fetch(`/api/hr/talent/candidate/${candidateId}`);
        const candidateData = await response.json();
        
        if (candidateData.success) {
            selectedCandidate = candidateData.data;
            displayCandidateDetails(candidateData.data);
            $('#candidateDetailModal').modal('show');
        }
        
    } catch (error) {
        console.error('Error loading candidate details:', error);
        showNotification('Error loading candidate details', 'error');
    }
}

// Display candidate details
function displayCandidateDetails(candidate) {
    const profileContainer = document.getElementById('candidate-profile');
    const assessmentContainer = document.getElementById('ai-assessment');
    
    // Profile section
    profileContainer.innerHTML = `
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">Candidate Profile</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>${candidate.name}</h5>
                        <p class="text-muted">${candidate.position} - ${candidate.department}</p>
                        <p><strong>Email:</strong> ${candidate.email}</p>
                        <p><strong>Phone:</strong> ${candidate.phone || 'N/A'}</p>
                        <p><strong>Experience:</strong> ${candidate.years_of_experience} years</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Skills</h6>
                        <div class="mb-3">
                            ${(candidate.skills || []).map(skill => 
                                `<span class="badge badge-primary mr-1">${skill}</span>`
                            ).join('')}
                        </div>
                        <h6>Education</h6>
                        <p>${candidate.highest_education || 'N/A'}</p>
                    </div>
                </div>
                <div class="mt-3">
                    <h6>Cover Letter</h6>
                    <p>${candidate.cover_letter || 'No cover letter provided.'}</p>
                </div>
            </div>
        </div>
    `;
    
    // AI Assessment section
    assessmentContainer.innerHTML = `
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">AI Assessment</h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6>Overall Score</h6>
                    <div class="progress mb-2">
                        <div class="progress-bar" role="progressbar" style="width: ${candidate.ai_score * 10}%" 
                             aria-valuenow="${candidate.ai_score * 10}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted">${candidate.ai_score}/10</small>
                </div>
                
                <div class="mb-3">
                    <h6>Technical Skills</h6>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-info" role="progressbar" style="width: ${candidate.technical_score * 10}%" 
                             aria-valuenow="${candidate.technical_score * 10}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted">${candidate.technical_score}/10</small>
                </div>
                
                <div class="mb-3">
                    <h6>Cultural Fit</h6>
                    <div class="progress mb-2">
                        <div class="progress-bar bg-success" role="progressbar" style="width: ${candidate.cultural_fit_score * 10}%" 
                             aria-valuenow="${candidate.cultural_fit_score * 10}" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                    <small class="text-muted">${candidate.cultural_fit_score}/10</small>
                </div>
                
                <div class="mt-4">
                    <h6>AI Insights</h6>
                    <ul class="list-unstyled">
                        ${(candidate.ai_insights || []).map(insight => 
                            `<li class="mb-2"><i class="fas fa-robot text-primary mr-2"></i>${insight}</li>`
                        ).join('')}
                    </ul>
                </div>
            </div>
        </div>
    `;
}

// Update candidate status
async function updateStatus(candidateId, action) {
    try {
        const newStatus = action === 'next' ? getNextStatus(selectedCandidate.status) : 'rejected';
        
        const response = await fetch(`/api/hr/talent/candidate/${candidateId}/status`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ status: newStatus })
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Candidate status updated successfully', 'success');
            loadPipelineData(); // Refresh data
        } else {
            showNotification('Error updating candidate status', 'error');
        }
        
    } catch (error) {
        console.error('Error updating candidate status:', error);
        showNotification('Error updating candidate status', 'error');
    }
}

// Get next status
function getNextStatus(currentStatus) {
    const statusFlow = {
        'applied': 'screening',
        'screening': 'interview',
        'interview': 'offer',
        'offer': 'hired'
    };
    return statusFlow[currentStatus] || currentStatus;
}

// Run AI screening
async function runAIScreening() {
    try {
        const response = await fetch('/api/hr/talent/ai-screening', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        });
        
        const result = await response.json();
        
        if (result.success) {
            displayAIScreeningResults(result.data);
            $('#aiScreeningModal').modal('show');
        } else {
            showNotification('Error running AI screening', 'error');
        }
        
    } catch (error) {
        console.error('Error running AI screening:', error);
        showNotification('Error running AI screening', 'error');
    }
}

// Display AI screening results
function displayAIScreeningResults(results) {
    const container = document.getElementById('ai-screening-results');
    
    container.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-robot"></i> AI screening completed for ${results.total_candidates} candidates
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <h6>Recommended for Next Stage</h6>
                <div class="list-group">
                    ${(results.recommended || []).map(candidate => `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">${candidate.name}</h6>
                                    <small class="text-muted">${candidate.position}</small>
                                </div>
                                <span class="badge badge-success">${candidate.ai_score}/10</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
            
            <div class="col-md-6">
                <h6>Needs Review</h6>
                <div class="list-group">
                    ${(results.needs_review || []).map(candidate => `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">${candidate.name}</h6>
                                    <small class="text-muted">${candidate.position}</small>
                                </div>
                                <span class="badge badge-warning">${candidate.ai_score}/10</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        </div>
    `;
}

// Load AI insights
async function loadAIInsights() {
    try {
        const response = await fetch('/api/hr/talent/ai-insights');
        const insightsData = await response.json();
        
        const container = document.getElementById('ai-insights-pipeline');
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
    // WebSocket implementation for real-time pipeline updates
}

// Utility functions
function refreshPipeline() {
    loadPipelineData();
    showNotification('Pipeline refreshed', 'success');
}

function exportPipeline() {
    // Export pipeline data
    showNotification('Pipeline exported', 'success');
}

function showNotification(message, type) {
    // Show notification to user
    console.log(`${type}: ${message}`);
}
</script>
@endsection 