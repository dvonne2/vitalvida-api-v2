<?php

namespace App\Jobs;

use App\Models\Lead;
use App\Models\User;
use App\Notifications\NewLeadAssigned;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class AssignLeadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Lead $lead) {}

    public function handle()
    {
        // AI Sales Manager Logic - Round Robin Assignment
        $agent = User::where('role', 'telesales')
                     ->where('is_active', true)
                     ->orderBy('last_assigned_at', 'asc')
                     ->first();

        if ($agent) {
            $this->lead->update([
                'assigned_to' => $agent->id,
                'status' => 'assigned',
                'assigned_at' => now()
            ]);

            $agent->update(['last_assigned_at' => now()]);

            // Send notification to agent
            $agent->notify(new NewLeadAssigned($this->lead));
        }
    }
} 