<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'isDevMode' => App::environment('local'),
        ]);
    }

    /**
     * Seed database and return default dev login credentials.
     */
    public function seedDevLogin(Request $request): RedirectResponse
    {
        abort_unless(App::environment('local'), 404);

        Artisan::call('db:seed', ['--force' => true]);

        return back()->with('devLoginSeed', [
            'message' => 'Seeder berhasil dijalankan.',
            'email' => 'admin@ocn.test',
            'password' => 'password',
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Logout from all devices by invalidating all sessions for the current user.
     */
    public function destroyAll(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => 'required|string|current_password',
        ]);

        Auth::guard('web')->logoutOtherDevices($validated['password']);

        return back()->with('flash', ['type' => 'success', 'message' => 'Sesi perangkat lain berhasil dihapus.']);
    }
}
