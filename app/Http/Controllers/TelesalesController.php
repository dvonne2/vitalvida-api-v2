<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Form;
use Illuminate\Http\Request;

class TelesalesController extends Controller
{
    public function dashboard()
    {
        $agent = auth()->user();
        
        // Get assigned leads with form information
        $assignedLeads = Lead::with(['form'])
            ->where('assigned_to', $agent->id)
            ->where('status', 'assigned')
            ->orderBy('created_at', 'desc')
            ->get();

        // Get recent activity
        $recentActivity = Lead::with(['form'])
            ->where('assigned_to', $agent->id)
            ->whereIn('status', ['contacted', 'quoted', 'converted', 'closed'])
            ->orderBy('updated_at', 'desc')
            ->limit(10)
            ->get();

        // Get statistics
        $stats = [
            'total_assigned' => Lead::where('assigned_to', $agent->id)->count(),
            'pending_contact' => Lead::where('assigned_to', $agent->id)->where('status', 'assigned')->count(),
            'in_progress' => Lead::where('assigned_to', $agent->id)->whereIn('status', ['contacted', 'quoted'])->count(),
            'converted_today' => Lead::where('assigned_to', $agent->id)
                ->where('status', 'converted')
                ->whereDate('updated_at', today())
                ->count(),
            'conversion_rate' => $this->getConversionRate($agent->id)
        ];

        // Get form performance for this agent
        $formPerformance = $this->getFormPerformance($agent->id);

        return view('telesales.dashboard', compact(
            'assignedLeads', 
            'recentActivity', 
            'stats', 
            'formPerformance'
        ));
    }

    public function leadDetails(Lead $lead)
    {
        // Ensure this lead is assigned to current agent
        if ($lead->assigned_to !== auth()->id()) {
            abort(403, 'This lead is not assigned to you.');
        }

        $lead->load(['form']);
        
        return view('telesales.lead-details', compact('lead'));
    }

    public function updateLeadStatus(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'status' => 'required|in:contacted,quoted,converted,closed,lost',
            'notes' => 'nullable|string',
            'follow_up_date' => 'nullable|date'
        ]);

        $lead->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'],
            'follow_up_date' => $validated['follow_up_date'],
            'last_contact_at' => now()
        ]);

        return redirect()->back()->with('success', 'Lead status updated successfully!');
    }

    private function getConversionRate($agentId)
    {
        $totalLeads = Lead::where('assigned_to', $agentId)->count();
        $convertedLeads = Lead::where('assigned_to', $agentId)->where('status', 'converted')->count();
        
        return $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0;
    }

    private function getFormPerformance($agentId)
    {
        return Lead::selectRaw('form_id, forms.name as form_name, 
                               COUNT(*) as total_leads,
                               SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) as converted_leads,
                               ROUND(SUM(CASE WHEN status = "converted" THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as conversion_rate')
            ->leftJoin('forms', 'leads.form_id', '=', 'forms.id')
            ->where('assigned_to', $agentId)
            ->groupBy('form_id', 'forms.name')
            ->having('total_leads', '>', 0)
            ->orderBy('total_leads', 'desc')
            ->get();
    }
} 