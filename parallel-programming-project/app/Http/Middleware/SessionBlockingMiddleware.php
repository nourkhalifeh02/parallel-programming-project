<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SessionBlockingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()->id;
        $cacheKey = "order_session_block_{$userId}";

        if (Cache::has($cacheKey)) {
            return response()->json([
                'message' => 'Your previous order is still being processed. Please wait.',
            ], 429);
        }

        Cache::put($cacheKey, true, now()->addSeconds(30));

        return $next($request);
    }
}
