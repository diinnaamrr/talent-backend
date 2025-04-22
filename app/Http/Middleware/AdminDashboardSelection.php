<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminDashboardSelection
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is logged in and is an admin
        if (auth()->check() && auth()->user()->hasRole('admin')) {
            // If no dashboard type is selected, redirect to selection page
            if (!session()->has('dashboard_type') && !$request->is('admin/choose-dashboard')) {
                return redirect()->route('admin.choose-dashboard');
            }
        }

        return $next($request);
    }
}
