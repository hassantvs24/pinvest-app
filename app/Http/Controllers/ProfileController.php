<?php

namespace App\Http\Controllers;

use App\Support\CommissionSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Show the current user's profile.
     */
    public function show(Request $request): View
    {
        return view('profile', [
            'user' => $request->user(),
            'commissionDue' => CommissionSettlementService::pendingDue($request->user()->id),
        ]);
    }

    /**
     * Update the current user's password.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', __('messages.password_updated'));
    }
}
