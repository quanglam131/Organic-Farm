<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-based access for the back office (Tuần 2, Tuần 7).
 *
 *   ->middleware('role:staff')        farm staff (and admins)
 *   ->middleware('role:sales')        sales / customer care (and admins)
 *   ->middleware('role:staff,sales')  either
 *   ->middleware('role:admin')        administrators only
 *
 * Administrators pass every check. Locked accounts are refused.
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || $user->is_active === false || ! $user->hasRole(...$roles)) {
            abort(403, 'Bạn không có quyền truy cập chức năng này.');
        }

        return $next($request);
    }
}
