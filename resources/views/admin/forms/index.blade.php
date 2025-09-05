@extends('layouts.admin')

@section('title', 'Form Management')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0 text-gray-800">Form Management</h1>
        <a href="{{ route('admin.forms.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Form
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Forms Grid -->
    <div class="row">
        @forelse($forms as $form)
        <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="font-weight-bold text-primary text-uppercase mb-1">
                                    {{ $form->name }}
                                </h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="{{ route('admin.forms.edit', $form) }}">
                                            <i class="fas fa-edit me-2"></i>Edit
                                        </a></li>
                                        <li><a class="dropdown-item" href="{{ route('admin.forms.preview', $form) }}" target="_blank">
                                            <i class="fas fa-eye me-2"></i>Preview
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" onclick="copyEmbedCode({{ $form->id }})">
                                            <i class="fas fa-code me-2"></i>Copy Embed Code
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form action="{{ route('admin.forms.duplicate', $form) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="dropdown-item">
                                                    <i class="fas fa-copy me-2"></i>Duplicate
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="{{ route('admin.forms.destroy', $form) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="fas fa-trash me-2"></i>Delete
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="text-xs font-weight-bold text-gray-600 text-uppercase mb-1">
                                Total Submissions
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                {{ number_format($form->total_submissions) }}
                            </div>
                            
                            <div class="mt-3">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    Last submission: {{ $form->last_submission_at ? $form->last_submission_at->diffForHumans() : 'Never' }}
                                </small>
                            </div>
                            
                            <div class="mt-2">
                                <span class="badge {{ $form->is_active ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $form->is_active ? 'Active' : 'Inactive' }}
                                </span>
                                <small class="text-muted ms-2">
                                    {{ $form->leads_count }} leads (30 days)
                                </small>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-wpforms fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex justify-content-between">
                        <a href="{{ route('admin.forms.edit', $form) }}" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit me-1"></i>Edit Form
                        </a>
                        <a href="{{ route('forms.show', $form) }}" target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="fas fa-external-link-alt me-1"></i>View Live
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="card shadow">
                <div class="card-body text-center py-5">
                    <i class="fas fa-wpforms fa-4x text-gray-300 mb-3"></i>
                    <h5 class="text-gray-600">No forms created yet</h5>
                    <p class="text-gray-500">Create your first form to start collecting leads</p>
                    <a href="{{ route('admin.forms.create') }}" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Create First Form
                    </a>
                </div>
            </div>
        </div>
        @endforelse
    </div>
</div>

<!-- Embed Code Modal -->
<div class="modal fade" id="embedCodeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Embed Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">iFrame Code:</label>
                    <textarea id="iframeCode" class="form-control" rows="3" readonly></textarea>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyToClipboard('iframeCode')">
                        Copy iFrame Code
                    </button>
                </div>
                <div class="mb-3">
                    <label class="form-label">Direct URL:</label>
                    <input type="text" id="formUrl" class="form-control" readonly>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="copyToClipboard('formUrl')">
                        Copy URL
                    </button>
                </div>
                <div>
                    <label class="form-label">Form ID:</label>
                    <input type="text" id="formId" class="form-control" readonly>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyEmbedCode(formId) {
    fetch(`/admin/forms/${formId}/embed-code`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('iframeCode').value = data.iframe_code;
            document.getElementById('formUrl').value = data.form_url;
            document.getElementById('formId').value = data.form_id;
            new bootstrap.Modal(document.getElementById('embedCodeModal')).show();
        });
}

function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    document.execCommand('copy');
    
    // Show success message
    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = 'Copied!';
    btn.classList.add('btn-success');
    btn.classList.remove('btn-outline-primary');
    
    setTimeout(() => {
        btn.textContent = originalText;
        btn.classList.remove('btn-success');
        btn.classList.add('btn-outline-primary');
    }, 2000);
}
</script>
@endsection 