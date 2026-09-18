<?php

namespace Darvis\Signer\Http\Middleware;

use Closure;
use Darvis\Signer\Support\SignerConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatePortal
{
    public function __construct(
        protected SignerConfig $config,
    ) {}

    /**
     * Redirect guests to the portal login instead of the application login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard($this->config->portalGuard())->check()) {
            return redirect()->guest(route('signer.portal.login'));
        }

        return $next($request);
    }
}
