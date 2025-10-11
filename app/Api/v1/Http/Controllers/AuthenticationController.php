<?php

namespace App\Api\v1\Http\Controllers;

use App\Api\v1\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthenticationController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        /** @var string|false $token */
        $token = Auth::guard('api')->attempt($credentials);

        if ($token === false) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        /** @var string $token */
        return $this->respondWithToken($token);
    }

    public function me(): JsonResponse
    {
        return response()->json(Auth::guard('api')->user());
    }

    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Logged out']);
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::parseToken()->refresh();

            return $this->respondWithToken($token);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token refresh failed'], 401);
        }
    }

    protected function respondWithToken(string $token): JsonResponse
    {
        $ttlConfig = config('jwt.ttl');
        $ttlMinutes = is_numeric($ttlConfig) ? (int) $ttlConfig : 60;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => $ttlMinutes * 60,
        ]);
    }
}
