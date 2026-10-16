<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSessionNotExpired;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'is_active' => true])) {
            throw ValidationException::withMessages(['username' => 'Incorrect username or password.']);
        }

        if (! $request->user()->isOwner()) {
            Auth::logout();

            throw ValidationException::withMessages([
                'username' => 'Only the owner can use the dashboard. Operators use the FRT mobile app.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put(EnsureSessionNotExpired::SESSION_KEY, now()->getTimestamp());

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
