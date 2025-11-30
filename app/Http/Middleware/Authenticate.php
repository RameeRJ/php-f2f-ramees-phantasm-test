<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API requests, return null to prevent redirect
        // This will cause a 401 JSON response instead
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // For web requests, redirect to login page
        return route('login');
    }

    /**
     * Handle unauthenticated users for API requests
     */
    protected function unauthenticated($request, array $guards)
    {
        // Check if it's an API request
        if ($request->expectsJson() || $request->is('api/*')) {
            abort(response()->json([
                'success' => false,
                'message' => 'Unauthenticated. Please provide a valid token.'
            ], 401));
        }

        // For web requests, use default behavior
        parent::unauthenticated($request, $guards);
    }
}