<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TelesalesMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Check if user has telesales role
        $user = auth()->user();
        
        // For now, we'll allow any authenticated user to access telesales
        // In a real application, you might want to check for specific roles
        // if (!$user->hasRole('telesales')) {
        //     abort(403, 'Access denied. Telesales role required.');
        // }

        return $next($request);
    }
} 