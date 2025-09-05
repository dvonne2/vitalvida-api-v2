@extends('layouts.telesales')

@section('title', 'Lead Details: ' . $lead->customer_name)

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <!-- Lead Information -->
        <div class="col-lg-8">
            <div class="card shadow">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Lead Details</h6>
                    <div>
                        <span class="badge badge-{{ $lead->status_badge }} mr-2">{{ ucfirst($lead->status) }}</span>
                        @if($lead->form)
                        <span class="badge badge-info">{{ $lead->form->name }}</span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="mb-3">Customer Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Name:</strong></td>
                                    <td>{{ $lead->customer_name }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Phone:</strong></td>
                                    <td>
                                        <a href="tel:{{ $lead->customer_phone }}" class="text-decoration-none">
                                            {{ $lead->formatted_phone }}
                                        </a>
                                        <button class="btn btn-sm btn-outline-primary ml-2" 
                                                onclick="copyToClipboard('{{ $lead->customer_phone }}')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </td>
                                </tr>
                                @if($lead->customer_email)
                                <tr>
                                    <td><strong>Email:</strong></td>
                                    <td>
                                        <a href="mailto:{{ $lead->customer_email }}">{{ $lead->customer_email }}</a>
                                    </td>
                                </tr>
                                @endif
                                <tr>
                                    <td><strong>State:</strong></td>
                                    <td>{{ $lead->address ? explode(',', $lead->address)[0] : 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Address:</strong></td>
                                    <td>{{ $lead->address }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Source:</strong></td>
                                    <td>{{ $lead->source }}</td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5 class="mb-3">Order Information</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Product:</strong></td>
                                    <td>{{ $lead->product }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Payment Method:</strong></td>
                                    <td>{{ $lead->payment_method }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Delivery Type:</strong></td>
                                    <td>{{ $lead->delivery_preference }}</td>
                                </tr>
                                <tr>
                                    <td><strong>Delivery Cost:</strong></td>
                                    <td>₦{{ number_format($lead->delivery_cost) }}</td>
                                </tr>
                                @if($lead->promo_code)
                                <tr>
                                    <td><strong>Promo Code:</strong></td>
                                    <td><span class="badge badge-warning">{{ $lead->promo_code }}</span></td>
                                </tr>
                                @endif
                                <tr>
                                    <td><strong>Total Value:</strong></td>
                                    <td><strong class="text-success">₦{{ number_format($lead->total_value) }}</strong></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    @if($lead->form)
                    <div class="mt-4">
                        <h5 class="mb-3">Form Information</h5>
                        <div class="alert alert-info">
                            <div class="row">
                                <div class="col-md-8">
                                    <strong>Form Name:</strong> {{ $lead->form->name }}<br>
                                    <strong>Submitted:</strong> {{ $lead->created_at->format('M d, Y \a\t h:i A') }}<br>
                                    <strong>Time Since:</strong> {{ $lead->created_at->diffForHumans() }}
                                </div>
                                <div class="col-md-4 text-right">
                                    <a href="{{ route('forms.show', $lead->form) }}" target="_blank" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-external-link-alt mr-1"></i>View Form
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Actions & Status -->
        <div class="col-lg-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Quick Actions</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="tel:{{ $lead->customer_phone }}" class="btn btn-success">
                            <i class="fas fa-phone mr-2"></i>Call Customer
                        </a>
                        @if($lead->customer_email)
                        <a href="mailto:{{ $lead->customer_email }}" class="btn btn-primary">
                            <i class="fas fa-envelope mr-2"></i>Send Email
                        </a>
                        @endif
                        <a href="https://wa.me/{{ str_replace('+', '', $lead->customer_phone) }}" target="_blank" class="btn btn-success">
                            <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                        </a>
                    </div>
                </div>
            </div>

            <!-- Update Status -->
            <div class="card shadow mt-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Update Status</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('telesales.update-lead-status', $lead) }}">
                        @csrf
                        @method('PUT')
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select name="status" class="form-control" required>
                                <option value="contacted" {{ $lead->status === 'contacted' ? 'selected' : '' }}>Contacted</option>
                                <option value="quoted" {{ $lead->status === 'quoted' ? 'selected' : '' }}>Quoted</option>
                                <option value="converted" {{ $lead->status === 'converted' ? 'selected' : '' }}>Converted</option>
                                <option value="closed" {{ $lead->status === 'closed' ? 'selected' : '' }}>Closed</option>
                                <option value="lost" {{ $lead->status === 'lost' ? 'selected' : '' }}>Lost</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Add notes about this contact...">{{ $lead->notes }}</textarea>
                        </div>

                        <div class="form-group">
                            <label for="follow_up_date">Follow-up Date</label>
                            <input type="datetime-local" name="follow_up_date" class="form-control" 
                                   value="{{ $lead->follow_up_date ? $lead->follow_up_date->format('Y-m-d\TH:i') : '' }}">
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-save mr-2"></i>Update Status
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('telesales.dashboard') }}" class="btn btn-secondary">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success message
        event.target.innerHTML = '<i class="fas fa-check"></i>';
        event.target.classList.add('btn-success');
        
        setTimeout(() => {
            event.target.innerHTML = '<i class="fas fa-copy"></i>';
            event.target.classList.remove('btn-success');
        }, 2000);
    });
}
</script>
@endsection 