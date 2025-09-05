<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachReferralCode
{
    public function handle(Request $request, Closure $next): Response
    {
        // Check for ?ref=TOKEN in query string
        if ($request->has('ref')) {
            $token = $request->get('ref');
            
            // Set httpOnly SameSite=Lax cookie for 30 days
            cookie()->queue(cookie('ref_token', $token, 60 * 24 * 30, '/', null, false, true, false, 'lax'));
            
            // Also store in session for immediate access
            session(['ref_token' => $token]);
        }

        return $next($request);
    }
}
