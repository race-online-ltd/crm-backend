<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class RefreshJwtOnActivity
{
    private const REFRESH_WINDOW_SECONDS = 15 * 60;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $token = $request->bearerToken();

        if (!$token) {
            return $response;
        }

        try {
            $payload = JWTAuth::setToken($token)->getPayload();
            $expiresAt = (int) $payload->get('exp');
            $remainingSeconds = $expiresAt - now()->timestamp;

            if ($remainingSeconds <= 0) {
                return $response;
            }

            if ($remainingSeconds < self::REFRESH_WINDOW_SECONDS) {
                $newToken = JWTAuth::setToken($token)->refresh();

                $response->headers->set('Authorization', 'Bearer '.$newToken);
            }
        } catch (JWTException) {
            return $response;
        }

        return $response;
    }
}
