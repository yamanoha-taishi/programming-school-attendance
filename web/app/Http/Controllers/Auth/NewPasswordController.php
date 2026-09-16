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
        $staff = Staff::where('email', $validated['email'])->first();

        $matched = null;

        foreach ([['guardian', $guardian], ['staff', $staff]] as [$guard, $user]) {
            if (! $user) {
                continue;
            }

            $tokenRecord =
                DB::table('password_reset_tokens')
                    ->where('email', $validated['email'])
                    ->where('guard', $guard)
                    ->first();

            if ($tokenRecord && Hash::check($validated['token'], $tokenRecord->token)) {
                $matched = [
                    'user' => $user,
                    'guard' => $guard,
                    'tokenRecord' => $tokenRecord,
                ];
                break;
            }

        }

        if (! $matched) {
            throw ValidationException::withMessages([
                'email' => [__('auth.reset_token_invalid')],
            ]);
        }

        if (now()->diffInMinutes($matched['tokenRecord']->created_at, absolute: true) > 60) {
            throw ValidationException::withMessages([
                'email' => [__('auth.reset_token_expired')],
            ]);
        }

        $matched['user']->password = Hash::make($validated['password']);
        $matched['user']->save();

        DB::table('password_reset_tokens')
            ->where('email', $validated['email'])
            ->where('guard', $matched['guard'])
            ->delete();

        DB::table('sessions')
            ->where('auth_id', $matched['user']->id)
            ->where('guard', $matched['guard'])
            ->delete();

        return redirect()->route('login')->with('status', __('auth.reset_success'));
    }
}
