<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Owner-only: manage commission partners.
 */
class PartnerController extends Controller
{
    /**
     * List all partners with their totals.
     */
    public function index(): View
    {
        return view('owner.partners', [
            'partners' => User::query()
                ->partners()
                ->withCount(['sales as pending_sales_count' => fn ($q) => $q->where('status', 'pending')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Show the create form.
     */
    public function create(): View
    {
        return view('owner.partner-form', ['partner' => null]);
    }

    /**
     * Store a new partner.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(6)],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        User::query()->create([
            'name' => $validated['name'],
            'phone' => preg_replace('/[\s\-+]/', '', $validated['phone']),
            'email' => $validated['email'] ?? null,
            'password' => $validated['password'],
            'role' => UserRole::Partner,
            'commission_rate' => $validated['commission_rate'],
            'preferred_language' => 'bn',
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('owner.partners.index')->with('success', __('messages.partner_added'));
    }

    /**
     * Show the edit form.
     */
    public function edit(int $partner): View
    {
        return view('owner.partner-form', [
            'partner' => User::query()->partners()->findOrFail($partner),
        ]);
    }

    /**
     * Update a partner. Password stays unchanged when left empty.
     */
    public function update(Request $request, int $partner): RedirectResponse
    {
        $user = User::query()->partners()->findOrFail($partner);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::min(6)],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'phone' => preg_replace('/[\s\-+]/', '', $validated['phone']),
            'email' => $validated['email'] ?? null,
            'commission_rate' => $validated['commission_rate'],
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($validated['password'])) {
            $user->update(['password' => $validated['password']]);
        }

        return redirect()->route('owner.partners.index')->with('success', __('messages.partner_updated'));
    }

    /**
     * Delete a partner (and their entries via cascade).
     */
    public function destroy(int $partner): RedirectResponse
    {
        User::query()->partners()->findOrFail($partner)->delete();

        return back()->with('success', __('messages.partner_deleted'));
    }
}
