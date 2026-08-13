<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function updateProfile(Request $request)
    { 
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email, ' . $user->id,
            'phone' => 'nullable|string|max:20',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user,
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
         return response()->json([
            'message' => 'Current password is incorrect.'
         ], 422);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Password updated successfully.',
        ]);
    }

    public function updateAddress(Request $request)
    {
       $user = $request->user();
       
       $validated = $request->validate([
          'line1' => 'required|string|max:255',
          'city' => 'required|string|max:100',
          'state' => 'required|string|max:100',
          'postalCode' => 'nullable|string|max:20',
          'country' => 'required|string|max:100',
       ]);

       $user->update([
        'address' => $validated,
       ]);

       return response()->json([
        'message' => 'Address updated successfully.',
        'address' => $user->address,
       ]);
    }

    public function updateNotifications(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'orderUpdates' => 'boolean',
            'promotions' => 'boolean',
            'newsletter' => 'boolean',
            'smsAlerts' => 'boolean',
        ]);

        $user->update([
            'notifications' => $validated,
        ]);

        return response()->json([
            'message' => 'Notification preferences updated',
            'notifications' => $user->notifications,
        ]);
    }

    public function updatePrivacy(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'shareActivity' => 'boolean',
            'personalizedAds' => 'boolean',
        ]);

        $user->update([
            'privacy' => $validated,
        ]);

        return response()->json([
            'message' => 'Privacy preferences updated',
            'privacy' => $user->privacy,
        ]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => 'required|string',
        ]);

        if (!Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Password is incorrect.'
            ], 422);
        }

        //Invalidate every active session before deleting
        $user->tokens()->delete();
        $user->delete();

        return response()->json([
            'message' => 'Account deleted successfully.'
        ]);
    }
}
