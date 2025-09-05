@props(['notifications'])

<div class="dropdown no-arrow">
    <a class="dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-bell fa-fw"></i>
        @if($notifications->count() > 0)
            <span class="badge badge-danger badge-counter">{{ $notifications->count() }}</span>
        @endif
    </a>
    
    <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="alertsDropdown">
        <h6 class="dropdown-header">
            Notifications Center
        </h6>
        
        @forelse($notifications->take(5) as $notification)
            <a class="dropdown-item d-flex align-items-center" href="{{ $notification->data['action_url'] ?? '#' }}">
                <div class="mr-3">
                    <div class="icon-circle bg-primary">
                        <i class="fas fa-user-plus text-white"></i>
                    </div>
                </div>
                <div>
                    <div class="small text-gray-500">{{ $notification->created_at->diffForHumans() }}</div>
                    <span class="font-weight-bold">{{ $notification->data['message'] ?? 'New notification' }}</span>
                </div>
            </a>
        @empty
            <div class="dropdown-item text-center py-3">
                <i class="fas fa-bell-slash fa-2x text-gray-300 mb-2"></i>
                <p class="text-gray-500 mb-0">No new notifications</p>
            </div>
        @endforelse
        
        @if($notifications->count() > 5)
            <a class="dropdown-item text-center small text-gray-500" href="#">
                Show all notifications
            </a>
        @endif
    </div>
</div> 