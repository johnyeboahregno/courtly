<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Reject anyone who is not a platform super admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->isSuperAdmin(),
            403,
            'Super admin access required.'
        );

        return $next($request);
    }
}
