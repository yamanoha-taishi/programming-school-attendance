<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetLinkMail;
use App\Mail\PasswordResetNotRegisteredMail;
use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $guardian = Guardian::where('email', $validated['email'])->first();
        $staff = Staff::where('email', $validated['email'])->first();

        if (! $guardian && ! $staff) {
            Mail::to($validated['email'])->send(new PasswordResetNotRegisteredMail);

            return back()->with('status', __('auth.reset_link_sent'));
        }

        foreach ([['guardian', $guardian], ['staff', $staff]] as [$guard, $user]) {
            if (! $user) {
                continue;
            }

            $token = Str::random(64);

            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $validated['email'], 'guard' => $guard],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $resetUrl = route('password.reset', [
                'token' => $token,
                'email' => $validated['email'],
            ]);

            Mail::to($validated['email'])->send(new PasswordResetLinkMail($resetUrl));
        }

        return back()->with('status', __('auth.reset_link_sent'));
    }
}
