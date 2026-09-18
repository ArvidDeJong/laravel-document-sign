<?php

namespace Darvis\Signer\Http\Controllers\Portal;

use Darvis\Signer\Support\SignerConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        protected SignerConfig $config,
    ) {}

    public function create(): View|RedirectResponse
    {
        if (Auth::guard($this->config->portalGuard())->check()) {
            return redirect()->route('signer.portal.dashboard');
        }

        return view('signer::portal.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $guard = Auth::guard($this->config->portalGuard());

        if (! $guard->attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('signer.portal.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard($this->config->portalGuard())->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('signer.portal.login');
    }
}
