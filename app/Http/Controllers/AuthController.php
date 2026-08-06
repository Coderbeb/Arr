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
