<?php

namespace App\Http\Middleware;

use App\Models\TelesalesAgent;
use Closure;
use Illuminate\Http\Request;

class TelesalesAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $agentId = $request->route('agentId') ?? $request->input('agent_id');
        
        if (!$agentId) {
            return response()->json(['error' => 'Agent ID required'], 400);
        }
        
        $agent = TelesalesAgent::find($agentId);
        
        if (!$agent || $agent->status !== 'active') {
            return response()->json(['error' => 'Invalid or inactive agent'], 403);
        }
        
        // Add agent to request for easy access
        $request->merge(['agent' => $agent]);
        
        return $next($request);
    }
} 