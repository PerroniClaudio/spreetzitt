<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdminOrHasSelectedCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->user();
        // Check if the user is admin or has a selected company
        if (! $authUser || ! ($authUser->is_admin || $authUser->selectedCompany())) {
            return response()->json(['message' => 'No company selected, nor admin user'], 403);
        }

        return $next($request);
    }
}
