<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Sandbox only: passwordless login that signs in the seeded "admin" user. Never use this in production.
 */
class LoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user instanceof User && $user->hasRole('admin')) {
            return redirect()->route('home');
        }

        return view('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $admin = User::query()->where('email', 'admin@sandbox.test')->first();

        if (! $admin) {
            return redirect()->route('login')
                ->withErrors(['login' => 'The admin user does not exist. Run "php artisan migrate".']);
        }

        Auth::login($admin);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
