<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    
    
    public function handle(Request $request, Closure $next): Response
    {

    $user = auth()->user();
    
    if (!$user || !$user->is_verified) {
        return response()->json([
            'message' => 'Please verify your email first'
        ], 403);
    }

        return $next($request);
    }
}
