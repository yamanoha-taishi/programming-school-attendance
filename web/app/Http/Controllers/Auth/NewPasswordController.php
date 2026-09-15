<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request, string $token): Response
    {
        return Inertia::render('auth/reset-password', [
            'token' => $token,
            'email' => $request->query('email'),
            'passwordRules' => '',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $guardian = Guardian::where('email', $validated['email'])->first();
        $staff = $guardian ? null : Staff::where('email', $validated['email'])->first();
        $user = $guardian ?? $staff;
        $guard = $guardian ? 'guardian' : 'staff';

        $tokenRecord = $user
            ? DB::table('password_reset_tokens')
                ->where('email', $validated['email'])
                ->where('guard', $guard)
                ->first()
            : null;

        if (! $user || ! $tokenRecord || ! Hash::check($validated['token'], $tokenRecord->token)) {
            throw ValidationException::withMessages([
                'email' => [__('auth.reset_token_invalid')],
            ]);
        }

        if (now()->diffInMinutes($tokenRecord->created_at) > 60) {
            throw ValidationException::withMessages([
                'email' => [__('auth.reset_token_expired')],
            ]);
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->where('guard', $guard)
            ->delete();

        return redirect()->route('login')->with('status', __('auth.reset_success'));
    }
}
