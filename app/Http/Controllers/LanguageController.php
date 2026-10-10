<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LanguageController extends Controller
{
    /**
     * Show the language picker (after first login / registration,
     * and anytime from the profile page).
     */
    public function show(): View
    {
        return view('auth.select-language');
    }

    /**
     * Save the user's language choice permanently on their profile.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'language' => ['required', 'in:'.implode(',', config('inventory.languages'))],
        ]);

        $request->user()->update(['preferred_language' => $validated['language']]);

        return redirect()->route('dashboard')->with('success', __('messages.language_updated'));
    }
}
