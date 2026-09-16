<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('auth/login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'member_code' => 'required',
            'password' => 'required',
        ]);

        // 入力された会員番号が保護者のものかチェック
        $guardian = Guardian::where('member_code', $validated['member_code'])->first();

        if ($guardian && Hash::check($validated['password'], $guardian->password)) {
            Auth::guard('guardian')->login($guardian);
            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        // 保護者ではなかった場合、スタッフのものかチェック
        $staff = Staff::where('member_code', $validated['member_code'])->first();

        if ($staff && Hash::check($validated['password'], $staff->password)) {
            Auth::guard('staff')->login($staff);
            $request->session()->regenerate();

            return redirect()->intended('/');
        }

        // 保護者・スタッフのどちらにも一致しなかった場合
        throw ValidationException::withMessages([
            'member_code' => [__('auth.failed')],
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        if (Auth::guard('guardian')->check()) {
            Auth::guard('guardian')->logout();
        }

        if (Auth::guard('staff')->check()) {
            Auth::guard('staff')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
