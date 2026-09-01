<?php

namespace App\Http\Controllers;

use App\Services\DemoSessionService;
use App\Services\DictationSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StartDictationSessionController
{
    public function __invoke(
        Request $request,
        DemoSessionService $sessions,
        DictationSessionService $dictation,
    ): JsonResponse {
        $session = $sessions->heartbeat($request);
        $sessions->queueCookie($session);

        $limit = (int) config('supportflow.dictation.sessions_per_hour', 20);
        $sessionKey = 'dictation|'.$session->id;
        $ipKey = 'dictation-ip|'.$request->ip();

        if (RateLimiter::tooManyAttempts($sessionKey, $limit) || RateLimiter::tooManyAttempts($ipKey, $limit)) {
            return response()->json([
                'message' => 'Microphone dictation is limited to '.$limit.' sessions per hour in this demo.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($sessionKey, 3600);
        RateLimiter::hit($ipKey, 3600);

        try {
            return response()->json($dictation->mint($session));
        } catch (RuntimeException $exception) {
            RateLimiter::clear($sessionKey);
            RateLimiter::clear($ipKey);

            return response()->json([
                'message' => $exception->getMessage(),
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (Throwable) {
            RateLimiter::clear($sessionKey);
            RateLimiter::clear($ipKey);

            return response()->json([
                'message' => 'Dictation is unavailable right now.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
