<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleRegistration
{
    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'registration-' . $request->ip();
        $maxAttempts = 5; // 5 registrations
        $decayMinutes = 60; // per hour

        if ($this->limiter->tooManyAttempts($key, $maxAttempts, $decayMinutes)) {
            $retryAfter = $this->limiter->availableIn($key);
            
            return response()->json([
                'message' => 'Terlalu banyak percobaan registrasi. Silakan coba lagi dalam ' . $retryAfter . ' detik.',
                'retry_after' => $retryAfter,
            ], 429);
        }

        $this->limiter->hit($key, $decayMinutes * 60);

        return $next($request);
    }
}
