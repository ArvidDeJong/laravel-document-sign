<?php

namespace Darvis\Signer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePortal
{
    /**
     * Redirect guests to the portal login instead of the application login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard(config('signer.portal.guard'))->check()) {
            return redirect()->guest(route('signer.portal.login'));
        }

        return $next($request);
    }
}
