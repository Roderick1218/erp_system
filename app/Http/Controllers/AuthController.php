<?php

namespace App\Http\Controllers;

use App\Models\LoginActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = $request->user();
            $user->forceFill(['last_login_at' => now()])->save();
            // A language picked on the login page becomes the account's language.
            if ($request->session()->has('locale')) {
                $user->forceFill(['locale' => $request->session()->get('locale')])->save();
            }
            $this->log($request, $user, 'login');

            return redirect()->intended(route('dashboard'));
        }

        $this->log($request, User::where('email', $credentials['email'])->first(), 'failed');

        return back()->withErrors(['email' => __('Email or password is incorrect, or the account is inactive.')])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $this->log($request, $request->user(), 'logout');
        $locale = $request->user()?->locale;
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('locale', $locale ?? 'en'); // login page stays in the same language

        return redirect()->route('login');
    }

    private function log(Request $request, ?User $user, string $event): void
    {
        LoginActivity::create([
            'user_id' => $user?->id,
            'email' => $user?->email ?? $request->input('email'),
            'event' => $event,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ]);
    }
}
