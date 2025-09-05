@extends('layouts.app')

@section('title', 'Telesales Dashboard - ' . $agent->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-12">
            <h1>Telesales Dashboard - {{ $agent->name }}</h1>
            
            <!-- Period Selector -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('telesales.dashboard', $agent->id) }}">
                        <div class="row">
                            <div class="col-md-3">
                                <select name="period" class="form-control">
                                    <option value="day" {{ request('period') == 'day' ? 'selected' : '' }}>By Day</option>
                                    <option value="week" {{ request('period') == 'week' ? 'selected' : '' }}>By Week</option>
                                    <option value="month" {{ request('period') == 'month' ? 'selected' : '' }}>By Month</option>
                                    <option value="quarter" {{ request('period') == 'quarter' ? 'selected' : '' }}>By Quarter</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="date" name="date" class="form-control" value="{{ request('date', now()->format('Y-m-d')) }}">
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- KPI Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h5>🔒 My Locked Bonus</h5>
                            <h3>₦{{ number_format($kpis['locked_bonus']) }}</h3>
                            <small>Unlocks in {{ $bonusInfo['days_remaining'] }} days</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5>📦 Orders Assigned</h5>
                            <h3>{{ $kpis['orders_assigned'] }}</h3>
                            <small>Need: 20+ orders</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5>✅ Deliveries</h5>
                            <h3>{{ $kpis['orders_delivered'] }}</h3>
                            <small>Successfully delivered</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5>📈 Delivery Rate</h5>
                            <h3>{{ $kpis['delivery_rate'] }}%</h3>
                            <small>Target: 70% | Bonus: ₦{{ number_format($kpis['weekly_bonus']) }}</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Qualification Status -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <div class="alert {{ $qualificationStatus['qualified'] ? 'alert-success' : 'alert-warning' }}">
                        @if($qualificationStatus['qualified'])
                            ✅ <strong>QUALIFIED</strong> - You've met this week's requirements!
                        @else
                            ⚠️ <strong>NOT QUALIFIED</strong> - 
                            @if($qualificationStatus['needs_more_orders'] > 0)
                                Need {{ $qualificationStatus['needs_more_orders'] }} more orders.
                            @endif
                            @if($qualificationStatus['needs_better_rate'])
                                Need to improve delivery rate to 70%.
                            @endif
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Orders Table -->
            <div class="card">
                <div class="card-header">
                    <h5>My Orders</h5>
                </div>
                <div class="card-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Customer</th>
                                <th>Product</th>
                                <th>Amount</th>
                                <th>Call Status</th>
                                <th>Delivery Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($orders as $order)
                            <tr>
                                <td>{{ $order->order_number }}</td>
                                <td>
                                    <strong>{{ $order->customer_name }}</strong><br>
                                    <small>{{ $order->customer_phone }}</small><br>
                                    <small>{{ $order->customer_location }}</small>
                                </td>
                                <td>
                                    @foreach($order->product_details as $item => $qty)
                                        {{ $qty }}x {{ ucfirst($item) }}<br>
                                    @endforeach
                                </td>
                                <td>₦{{ number_format($order->total_amount) }}</td>
                                <td>
                                    <span class="badge 
                                        @if($order->call_status == 'confirmed') badge-success
                                        @elseif($order->call_status == 'pending') badge-warning
                                        @else badge-secondary
                                        @endif">
                                        {{ ucfirst($order->call_status) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge 
                                        @if($order->delivery_status == 'delivered') badge-success
                                        @elseif($order->delivery_status == 'assigned') badge-info
                                        @else badge-secondary
                                        @endif">
                                        {{ ucfirst($order->delivery_status) }}
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="openCallModal({{ $order->id }})">
                                        📞 Call
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="openDetailsModal({{ $order->id }})">
                                        📋 Details
                                    </button>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Call Modal -->
<div class="modal fade" id="callModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📞 Calling Customer</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="callModalContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">📋 Order Details</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div id="detailsModalContent">
                    <!-- Content loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openCallModal(orderId) {
    $('#callModal').modal('show');
    $('#callModalContent').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
    
    $.get(`/api/orders/${orderId}/call-interface`, function(data) {
        $('#callModalContent').html(data);
    });
}

function openDetailsModal(orderId) {
    $('#detailsModal').modal('show');
    $('#detailsModalContent').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
    
    $.get(`/api/telesales/{{ $agent->id }}/orders/${orderId}/details`, function(data) {
        $('#detailsModalContent').html(renderOrderDetails(data));
    });
}

function renderOrderDetails(data) {
    // Render order details and available agents
    return `
        <div class="row">
            <div class="col-md-6">
                <h6>Customer Information</h6>
                <p><strong>Name:</strong> ${data.order.customer_name}</p>
                <p><strong>Phone:</strong> ${data.order.customer_phone}</p>
                <p><strong>Location:</strong> ${data.order.customer_location}</p>
            </div>
            <div class="col-md-6">
                <h6>Available Delivery Agents</h6>
                ${data.available_agents.map(agent => `
                    <div class="border p-2 mb-2">
                        <strong>${agent.name}</strong> - ${agent.status}
                        <br>Stock: ${JSON.stringify(agent.current_stock)}
                        <br><button class="btn btn-sm btn-success" onclick="assignAgent(${data.order.id}, ${agent.id})">Assign</button>
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

function assignAgent(orderId, agentId) {
    $.post(`/api/orders/${orderId}/assign-da`, {agent_id: agentId}, function(response) {
        alert('Agent assigned successfully!');
        location.reload();
    }).fail(function(error) {
        alert('Failed to assign agent: ' + error.responseJSON.message);
    });
}
</script>
@endsection 