<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks customer-management routes until the admin has entered their PIN.
 *
 * The unlock is stored on the SERVER (in the cache), tied to this exact login token,
 * so it cannot be faked from the browser and a stolen different token is not unlocked.
 * It slides: every allowed request keeps it alive, and it expires after TTL_MINUTES idle.
 */
class EnsureAdminPinVerified
{
    public const TTL_MINUTES = 30;

    public static function key(Request $request): ?string
    {
        $token = $request->bearerToken();
        $user = $request->user();

        if (!$token || !$user) {
            return null;
        }

        return 'admin_pin_unlocked:' . $user->id . ':' . hash('sha256', $token);
    }

    public static function unlock(Request $request): void
    {
        if ($key = self::key($request)) {
            Cache::put($key, true, now()->addMinutes(self::TTL_MINUTES));
        }
    }

    public static function isUnlocked(Request $request): bool
    {
        $key = self::key($request);

        return $key !== null && Cache::has($key);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!self::isUnlocked($request)) {
            return response()->json([
                'message' => 'Enter your admin PIN to continue.',
                'code' => 'pin_required',
            ], 403);
        }

        self::unlock($request); // keep the window sliding while the admin is active

        return $next($request);
    }
}