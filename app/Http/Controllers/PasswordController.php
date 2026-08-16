<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->forceFill(['password' => Hash::make($data['password']), 'remember_token' => null])->save();
        $request->session()->regenerate();

        return back()->with('success', 'Password changed and active sessions were refreshed.');
    }
}
