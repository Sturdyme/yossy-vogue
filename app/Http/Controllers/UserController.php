<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminPinVerified;
use App\Jobs\ExportUserDataJob;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    private const PIN_MAX_ATTEMPTS = 5;
    private const PIN_LOCK_SECONDS = 900; // 15 minutes

    /**
     * The signed-in user, including the profile fields the Profile page needs.
     */
    public function me(Request $request)
    {
        return response()->json(['user' => $request->user()->toAuthPayload()]);
    }

    /**
     * Customer list. Protected by the "admin.pin" middleware in routes/api.php.
     */
    public function index()
    {
        $users = User::latest()->paginate(20);
        $users->getCollection()->each->makeHidden(['admin_pin', 'password', 'remember_token']);

        return $users;
    }

    /* ------------------------------------------------------------------ */
    /* Admin PIN                                                           */
    /* ------------------------------------------------------------------ */

    private function pinKey(User $user): string
    {
        return 'admin-pin:' . $user->id;
    }

    /**
     * Checks a PIN against the stored hash while counting wrong guesses.
     * After PIN_MAX_ATTEMPTS wrong guesses the admin is locked out for PIN_LOCK_SECONDS,
     * and every route that accepts a PIN shares this one counter.
     */
    private function pinMatches(User $user, string $pin): bool
    {
        $key = $this->pinKey($user);

        if (RateLimiter::tooManyAttempts($key, self::PIN_MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw new HttpResponseException(
                response()->json([
                    'message' => "Too many attempts. Try again in {$seconds} seconds.",
                    'retry_after' => $seconds,
                ], 429)->header('Retry-After', $seconds)
            );
        }

        if ($user->admin_pin && Hash::check($pin, $user->admin_pin)) {
            RateLimiter::clear($key);

            return true;
        }

        RateLimiter::hit($key, self::PIN_LOCK_SECONDS);

        return false;
    }

    public function adminPinStatus(Request $request)
    {
        return response()->json([
            'has_pin' => (bool) $request->user()->admin_pin,
            'unlocked' => EnsureAdminPinVerified::isUnlocked($request),
        ]);
    }

    public function setAdminPin(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'pin' => 'required|digits:4',
            'password' => 'required|string',
            'current_pin' => 'nullable|digits:4',
        ]);

        // A stolen login token alone must not be enough to create or replace the PIN.
        if (!Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Your account password is incorrect.',
            ]);
        }

        if ($user->admin_pin) {
            if (empty($validated['current_pin']) || !$this->pinMatches($user, $validated['current_pin'])) {
                throw ValidationException::withMessages([
                    'current_pin' => 'Your current PIN is incorrect.',
                ]);
            }
        }

        $user->forceFill(['admin_pin' => Hash::make($validated['pin'])])->save();

        // Setting the PIN counts as unlocking this session.
        EnsureAdminPinVerified::unlock($request);

        return response()->json(['message' => 'PIN saved.']);
    }

    public function verifyAdminPin(Request $request)
    {
        $validated = $request->validate([
            'pin' => 'required|digits:4',
        ]);

        $user = $request->user();

        if (!$this->pinMatches($user, $validated['pin'])) {
            $left = RateLimiter::remaining($this->pinKey($user), self::PIN_MAX_ATTEMPTS);

            return response()->json([
                'message' => $left > 0
                    ? "Incorrect PIN. {$left} " . ($left === 1 ? 'attempt' : 'attempts') . ' left.'
                    : 'Incorrect PIN.',
                'attempts_left' => $left,
            ], 403);
        }

        // Remember on the server that THIS login token passed the PIN check.
        EnsureAdminPinVerified::unlock($request);

        return response()->json([
            'message' => 'PIN verified successfully',
            'verified' => true,
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => 'required|in:customer,admin',
        ]);

        if ($user->role === 'admin' && $validated['role'] !== 'admin') {
            return response()->json([
                'message' => 'Existing administrators cannot be demoted.',
            ], 403);
        }

        $user->forceFill(['role' => $validated['role']])->save();

        return response()->json($user->makeHidden(['admin_pin']));
    }

    /* ------------------------------------------------------------------ */
    /* Own account                                                         */
    /* ------------------------------------------------------------------ */

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|min:3|max:255',
            'email' => 'sometimes|email',
            'phone' => 'nullable|string|max:20',
        ]);

        // The email was verified with a code, so it cannot be swapped from here.
        if (isset($validated['email']) && strtolower($validated['email']) !== strtolower($user->email)) {
            throw ValidationException::withMessages([
                'email' => 'Your email address cannot be changed here.',
            ]);
        }

        $user->fill(collect($validated)->only(['name', 'phone'])->all())->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => $user->toAuthPayload(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed', 'max:72', Password::min(8)->letters()->numbers()],
        ]);

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Current password is incorrect.',
            ]);
        }

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'Choose a password that is different from your current one.',
            ]);
        }

        $user->forceFill(['password' => Hash::make($validated['password'])])->save();

        // Sign out every other device; keep the one that just changed the password.
        $current = $user->currentAccessToken();
        $user->tokens()
            ->when($current && isset($current->id), fn ($query) => $query->where('id', '!=', $current->id))
            ->delete();

        return response()->json([
            'message' => 'Password updated successfully. Other devices were signed out.',
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
            'message' => 'Notification preferences updated.',
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
            'message' => 'Privacy preferences updated.',
            'privacy' => $user->privacy,
        ]);
    }

    public function exportData(Request $request)
    {
        $user = $request->user();

        // Queued: a queue worker must be running or the email is never sent.
        ExportUserDataJob::dispatch($user->id);

        return response()->json([
            'message' => 'Your data export is being prepared and will be emailed to you shortly.',
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
                'message' => 'Password is incorrect.',
            ], 422);
        }

        // Never leave the store without an administrator.
        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return response()->json([
                'message' => 'You are the only administrator. Promote another admin before deleting this account.',
            ], 422);
        }

       $user->anonymizeAndDelete();

        return response()->json([
            'message' => 'Account deleted successfully.',
        ]);
    }
}
