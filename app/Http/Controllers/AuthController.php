<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $request->validate([
            'mobile_number' => 'required|string|max:15|unique:users,mobile_number',
            'full_name'     => 'required|string|max:100',
            'date_of_birth' => 'required|date',
            'password'      => 'required|string|min:6',
            'upi_id'        => 'nullable|string|max:100',
            'upi_app'       => 'nullable|string|in:gpay,phonepe,paytm,bhim',
            'referral_code' => 'nullable|string|max:10',
        ]);

        $settings = \App\Models\PlatformSetting::first();
        if ($settings && !$settings->registration_open) {
            return response()->json(['error' => 'Registrations are currently closed by the administrator.'], 403);
        }

        $referredBy = null;
        if ($request->referral_code) {
            $referrer = User::where('referral_code', strtoupper($request->referral_code))->first();
            if ($referrer) {
                $referredBy = $referrer->id;
            }
        }

        $user = User::create([
            'id'             => (string) Str::uuid(),
            'mobile_number'  => $request->mobile_number,
            'full_name'      => $request->full_name,
            'date_of_birth'  => $request->date_of_birth,
            'password_hash'  => Hash::make($request->password),
            'upi_id'         => $request->upi_id,
            'upi_app'        => $request->upi_app ?? 'gpay',
            'role'           => 'user',
            'status'         => 'active',
            'wallet_balance' => 0.00,
            'escrow_balance' => 0.00,
            'reputation_score' => 100,
            'referred_by'    => $referredBy,
            'created_at'     => Carbon::now(),
        ]);

        Auth::login($user);

        return response()->json([
            'message' => 'Registration successful',
            'user'    => $user,
        ], 201);
    }

    /**
     * User login with DOB verification & security lockout
     */
    public function login(Request $request)
    {
        $request->validate([
            'mobile_number' => 'required|string',
            'password'      => 'required|string',
        ]);

        $user = User::where('mobile_number', $request->mobile_number)->first();

        if (!$user) {
            return response()->json(['error' => 'Invalid mobile number or password'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['error' => 'Your account is suspended or banned'], 403);
        }

        // Verify password
        if (!Hash::check($request->password, $user->password_hash)) {
            return response()->json(['error' => 'Invalid mobile number or password'], 401);
        }

        // Success: update last login
        $user->last_login = Carbon::now();
        $user->save();

        Auth::login($user);

        return response()->json([
            'message' => 'Login successful',
            'user'    => $user,
        ]);
    }

    /**
     * Reset password via date of birth verification (for normal users only)
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'mobile_number' => 'required|string',
            'date_of_birth' => 'required|date',
            'new_password'  => 'required|string|min:6',
        ]);

        $user = User::where('mobile_number', $request->mobile_number)->first();

        if (!$user) {
            return response()->json(['error' => 'No account found with this mobile number.'], 404);
        }

        // Block staff and admin roles from self-reset
        if (in_array($user->role, ['super_account', 'assistance', 'super_admin'])) {
            return response()->json(['error' => 'Staff accounts cannot self-reset. Contact the Super Admin to reset your password.'], 403);
        }

        // Check lockout
        if ($user->dob_lockout_until && Carbon::now()->lt($user->dob_lockout_until)) {
            $minutesLeft = Carbon::now()->diffInMinutes($user->dob_lockout_until, false);
            return response()->json([
                'error' => "Too many failed attempts. Try again in {$minutesLeft} minute(s).",
                'locked_until' => $user->dob_lockout_until->toISOString(),
            ], 429);
        }

        // Verify DOB
        $storedDob = Carbon::parse($user->date_of_birth)->format('Y-m-d');
        $inputDob  = Carbon::parse($request->date_of_birth)->format('Y-m-d');

        if ($storedDob !== $inputDob) {
            $attempts = ($user->failed_dob_attempts ?? 0) + 1;
            $user->failed_dob_attempts = $attempts;

            if ($attempts >= 5) {
                $user->dob_lockout_until = Carbon::now()->addMinutes(30);
                $user->save();
                return response()->json([
                    'error' => 'Too many failed attempts. Your account is locked for 30 minutes.',
                    'locked_until' => $user->dob_lockout_until->toISOString(),
                ], 429);
            }

            $user->save();
            $remaining = 5 - $attempts;
            return response()->json([
                'error' => "Date of birth does not match. {$remaining} attempt(s) remaining.",
            ], 401);
        }

        // DOB matched — reset password
        $user->password_hash = Hash::make($request->new_password);
        $user->failed_dob_attempts = 0;
        $user->dob_lockout_until = null;
        $user->save();

        return response()->json(['message' => 'Password reset successfully! You can now login with your new password.']);
    }

    /**
     * Fetch current user profile
     */
    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()]);
    }

    /**
     * User logout
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out successfully']);
    }
}
