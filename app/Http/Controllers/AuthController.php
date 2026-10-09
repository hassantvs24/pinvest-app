<?php

namespace App\Http\Controllers;

use App\Models\RegistrationAllow;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the login page (single identifier: email OR mobile number).
     */
    public function showLogin(): View
    {
        return view('auth.login');
    }

    /**
     * Handle login. Identifier matches email when it contains "@",
     * otherwise the phone number (digits, spaces and dashes stripped).
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($credentials['identifier']);
        $field = str_contains($identifier, '@') ? 'email' : 'phone';
        $value = $field === 'phone'
            ? preg_replace('/[\s\-+]/', '', $identifier)
            : $identifier;

        $user = User::query()->where($field, $value)->first();

        if (! $user || ! Auth::attempt(['email' => $user->email, 'password' => $credentials['password']])) {
            return back()
                ->withInput($request->only('identifier'))
                ->withErrors(['identifier' => __('messages.invalid_credentials')]);
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('identifier'))
                ->withErrors(['identifier' => __('messages.inactive_account')]);
        }

        $request->session()->regenerate();

        // First login: pick a language before anything else.
        if ($user->preferred_language === null) {
            return redirect()->route('language.show');
        }

        return redirect()->route('dashboard');
    }

    /**
     * Show the registration page (creates a partner account).
     */
    public function showRegister(): View
    {
        return view('auth.register');
    }

    /**
     * Register a new partner. Owners can never self-register (seeder only).
     * Only phone numbers / emails the owner has explicitly allowed may
     * register, and each allowance works exactly once.
     */
    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $normalizedPhone = preg_replace('/[\s\-+]/', '', $validated['phone']);
        $email = $validated['email'] ?? null;

        $allowance = RegistrationAllow::query()
            ->unused()
            ->where(function ($query) use ($normalizedPhone, $email): void {
                $query->where('phone', $normalizedPhone);
                if ($email) {
                    $query->orWhere('email', $email);
                }
            })
            ->first();

        if (! $allowance) {
            return back()
                ->withInput($request->only('name', 'phone', 'email'))
                ->withErrors(['phone' => __('messages.registration_not_allowed')]);
        }

        $user = User::query()->create([
            'name' => $validated['name'],
            'phone' => preg_replace('/[\s\-+]/', '', $validated['phone']),
            'email' => $validated['email'] ?? null,
            'password' => $validated['password'],
            'role' => UserRole::Partner,
            'commission_rate' => 0,
            'is_active' => true,
        ]);

        $allowance->markUsed();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('language.show');
    }

    /**
     * Log the user out (POST only).
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
