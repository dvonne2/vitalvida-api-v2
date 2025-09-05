@extends('layouts.app')

@section('title', 'Document Manager')

@section('content')
<div class="container-fluid">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">Document Manager</h1>
                    <p class="text-muted mb-0">Upload, manage, and track document access</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary" onclick="refreshDocuments()">
                        <i class="fas fa-sync-alt"></i> Refresh
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-upload"></i> Upload Document
                    </button>
                    <button class="btn btn-primary" onclick="bulkExport()">
                        <i class="fas fa-download"></i> Bulk Export
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Document Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Documents</h6>
                            <h3 class="mb-0">30</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Completed</h6>
                            <h3 class="mb-0">28</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">In Progress</h6>
                            <h3 class="mb-0">2</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Completion Rate</h6>
                            <h3 class="mb-0">93.3%</h3>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-percentage fa-2x"></i>
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
                        <div class="col-md-2 mb-3">
                            <div class="category-filter active" data-category="all">
                                <div class="category-icon bg-primary">
                                    <i class="fas fa-th"></i>
                                </div>
                                <div class="category-name">All Documents</div>
                                <div class="category-count">30</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="category-filter" data-category="financials">
                                <div class="category-icon bg-success">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="category-name">Financials</div>
                                <div class="category-count">8</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="category-filter" data-category="operations">
                                <div class="category-icon bg-info">
                                    <i class="fas fa-cogs"></i>
                                </div>
                                <div class="category-name">Operations</div>
                                <div class="category-count">6</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="category-filter" data-category="governance">
                                <div class="category-icon bg-warning">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="category-name">Governance</div>
                                <div class="category-count">10</div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="category-filter" data-category="vision">
                                <div class="category-icon bg-secondary">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <div class="category-name">Vision</div>
                                <div class="category-count">6</div>
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
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list text-secondary"></i>
                            Document List
                        </h5>
                        <div class="d-flex gap-2">
                            <input type="text" class="form-control form-control-sm" placeholder="Search documents..." id="documentSearch">
                            <select class="form-select form-select-sm" id="statusFilter">
                                <option value="">All Status</option>
                                <option value="complete">Complete</option>
                                <option value="in_progress">In Progress</option>
                                <option value="not_ready">Not Ready</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="documentsTable">
                            <thead>
                                <tr>
                                    <th>
                                        <input type="checkbox" id="selectAll">
                                    </th>
                                    <th>Document</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Confidentiality</th>
                                    <th>Last Updated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="document-checkbox" value="1">
                                    </td>
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
                                    <td><span class="badge bg-warning">Restricted</span></td>
                                    <td>2 hours ago</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewDocument(1)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" onclick="downloadDocument(1)" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewAccessLog(1)" title="Access Log">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="document-checkbox" value="2">
                                    </td>
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
                                    <td><span class="badge bg-danger">Confidential</span></td>
                                    <td>1 day ago</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewDocument(2)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" onclick="downloadDocument(2)" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewAccessLog(2)" title="Access Log">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="document-checkbox" value="3">
                                    </td>
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
                                    <td><span class="badge bg-warning">Restricted</span></td>
                                    <td>4 hours ago</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="viewDocument(3)" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" onclick="downloadDocument(3)" title="Download">
                                                <i class="fas fa-download"></i>
                                            </button>
                                            <button class="btn btn-outline-info" onclick="viewAccessLog(3)" title="Access Log">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
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

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="uploadForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="documentTitle" class="form-label">Document Title</label>
                                <input type="text" class="form-control" id="documentTitle" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="documentCategory" class="form-label">Category</label>
                                <select class="form-select" id="documentCategory" required>
                                    <option value="">Select Category</option>
                                    <option value="financials">Financials</option>
                                    <option value="operations">Operations</option>
                                    <option value="governance">Governance</option>
                                    <option value="vision">Vision</option>
                                    <option value="oversight">Oversight</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="documentFile" class="form-label">File</label>
                                <input type="file" class="form-control" id="documentFile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" required>
                                <div class="form-text">Maximum file size: 10MB</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="confidentiality" class="form-label">Confidentiality Level</label>
                                <select class="form-select" id="confidentiality" required>
                                    <option value="public">Public</option>
                                    <option value="restricted">Restricted</option>
                                    <option value="confidential">Confidential</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="requiredInvestors" class="form-label">Required for Investors</label>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Master" id="master">
                            <label class="form-check-label" for="master">Master Readiness</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Tomi" id="tomi">
                            <label class="form-check-label" for="tomi">Tomi Governance</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Ron" id="ron">
                            <label class="form-check-label" for="ron">Ron Scale</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Thiel" id="thiel">
                            <label class="form-check-label" for="thiel">Thiel Strategy</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Andy" id="andy">
                            <label class="form-check-label" for="andy">Andy Tech</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Otunba" id="otunba">
                            <label class="form-check-label" for="otunba">Otunba Control</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Dangote" id="dangote">
                            <label class="form-check-label" for="dangote">Dangote Cost Control</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="Neil" id="neil">
                            <label class="form-check-label" for="neil">Neil Growth</label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="documentDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="documentDescription" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="uploadDocument()">Upload Document</button>
            </div>
        </div>
    </div>
</div>

<!-- Access Log Modal -->
<div class="modal fade" id="accessLogModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Document Access Log</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="accessLogContent">
                    <!-- Access log content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize document manager
document.addEventListener('DOMContentLoaded', function() {
    initializeDocumentManager();
});

// Initialize document manager functionality
function initializeDocumentManager() {
    // Category filter functionality
    document.querySelectorAll('.category-filter').forEach(filter => {
        filter.addEventListener('click', function() {
            // Remove active class from all filters
            document.querySelectorAll('.category-filter').forEach(f => f.classList.remove('active'));
            // Add active class to clicked filter
            this.classList.add('active');
            
            const category = this.dataset.category;
            filterDocuments(category);
        });
    });

    // Search functionality
    document.getElementById('documentSearch').addEventListener('input', function() {
        const searchTerm = this.value.toLowerCase();
        searchDocuments(searchTerm);
    });

    // Status filter functionality
    document.getElementById('statusFilter').addEventListener('change', function() {
        const status = this.value;
        filterByStatus(status);
    });

    // Select all functionality
    document.getElementById('selectAll').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.document-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
}

// Filter documents by category
function filterDocuments(category) {
    // Implementation for filtering documents by category
    console.log('Filtering by category:', category);
}

// Search documents
function searchDocuments(searchTerm) {
    // Implementation for searching documents
    console.log('Searching for:', searchTerm);
}

// Filter by status
function filterByStatus(status) {
    // Implementation for filtering by status
    console.log('Filtering by status:', status);
}

// Refresh documents
function refreshDocuments() {
    location.reload();
}

// Upload document
function uploadDocument() {
    const formData = new FormData();
    formData.append('title', document.getElementById('documentTitle').value);
    formData.append('category', document.getElementById('documentCategory').value);
    formData.append('file', document.getElementById('documentFile').files[0]);
    formData.append('confidentiality', document.getElementById('confidentiality').value);
    formData.append('description', document.getElementById('documentDescription').value);

    // Get selected investors
    const selectedInvestors = [];
    document.querySelectorAll('input[type="checkbox"]:checked').forEach(checkbox => {
        if (checkbox.id !== 'selectAll') {
            selectedInvestors.push(checkbox.value);
        }
    });
    formData.append('required_for_investors', JSON.stringify(selectedInvestors));

    fetch('/api/investor/documents/upload', {
        method: 'POST',
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Document uploaded successfully!');
            location.reload();
        } else {
            alert('Failed to upload document: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error uploading document:', error);
        alert('Error uploading document');
    });
}

// Bulk export
function bulkExport() {
    const selectedDocuments = [];
    document.querySelectorAll('.document-checkbox:checked').forEach(checkbox => {
        selectedDocuments.push(checkbox.value);
    });

    if (selectedDocuments.length === 0) {
        alert('Please select documents to export');
        return;
    }

    fetch('/api/investor/documents/bulk-export', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        },
        body: JSON.stringify({
            document_ids: selectedDocuments,
            format: 'zip',
            include_metadata: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Create download link
            const link = document.createElement('a');
            link.href = data.data.download_url;
            link.download = data.data.file_name;
            link.click();
        } else {
            alert('Failed to export documents: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error exporting documents:', error);
        alert('Error exporting documents');
    });
}

// View document
function viewDocument(documentId) {
    window.open(`/api/investor/documents/${documentId}`, '_blank');
}

// Download document
function downloadDocument(documentId) {
    window.open(`/api/investor/documents/${documentId}/download`, '_blank');
}

// View access log
function viewAccessLog(documentId) {
    fetch(`/api/investor/documents/${documentId}/access-log`, {
        headers: {
            'Authorization': 'Bearer ' + localStorage.getItem('token')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayAccessLog(data.data);
            new bootstrap.Modal(document.getElementById('accessLogModal')).show();
        } else {
            alert('Failed to load access log: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error loading access log:', error);
        alert('Error loading access log');
    });
}

// Display access log
function displayAccessLog(data) {
    const content = document.getElementById('accessLogContent');
    let html = `
        <h6>Document: ${data.document_title}</h6>
        <p class="text-muted">Total Accesses: ${data.total_accesses} | Unique Investors: ${data.unique_investors}</p>
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Investor</th>
                    <th>Action</th>
                    <th>IP Address</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
    `;

    data.access_log.forEach(log => {
        html += `
            <tr>
                <td>${log.investor_name}</td>
                <td><span class="badge bg-${log.action === 'downloaded' ? 'danger' : 'info'}">${log.action}</span></td>
                <td>${log.ip_address}</td>
                <td>${new Date(log.accessed_at).toLocaleString()}</td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>
    `;

    content.innerHTML = html;
}
</script>

<style>
.category-filter {
    text-align: center;
    padding: 1rem;
    border-radius: 0.5rem;
    border: 2px solid transparent;
    cursor: pointer;
    transition: all 0.3s ease;
}

.category-filter:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.category-filter.active {
    border-color: #007bff;
    background-color: #e3f2fd;
}

.category-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.5rem;
    color: white;
}

.category-name {
    font-weight: 500;
    margin-bottom: 0.25rem;
}

.category-count {
    font-size: 0.875rem;
    color: #6c757d;
}

.btn-group-sm .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

@media (max-width: 768px) {
    .category-filter {
        margin-bottom: 1rem;
    }
    
    .btn-group-sm .btn {
        padding: 0.375rem 0.75rem;
    }
}
</style>
@endsection 