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
        // Company admins may open a direct link before a company has been selected.
        // Their company memberships are still checked by the endpoint authorization.
        $hasCompanyAdminAccess = $authUser?->is_company_admin && $authUser->companies()->exists();

        if (! $authUser || ! ($authUser->is_admin || $authUser->selectedCompany() || $hasCompanyAdminAccess)) {
            return response()->json(['message' => 'No company selected, nor admin user'], 403);
        }

        return $next($request);
    }
}
