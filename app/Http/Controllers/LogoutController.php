<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Standard Laravel logout: invalidates the session and regenerates both
 * the session id and CSRF token (exactly what a scaffolded
 * Breeze/Fortify logout controller does — this app has neither
 * installed), then bounces to the new standalone /login page. Reached
 * from a plain POST form in the TALL-stack shell's avatar menu
 * (resources/views/components/tallstack/app.blade.php), not a Livewire
 * action, so it works identically from any authenticated page.
 */
class LogoutController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
