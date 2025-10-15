<?php

namespace App\Api\v1\Http\Controllers;

use App\Api\v1\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * @OA\Info(
 *   title="Collaborators API",
 *   version="1.0.0"
 * )
 *
 * @OA\Server(
 *   url="/",
 *   description="Base server"
 * )
 *
 * @OA\SecurityScheme(
 *   securityScheme="bearerAuth",
 *   type="http",
 *   scheme="bearer",
 *   bearerFormat="JWT"
 * )
 */
class AuthenticationController extends Controller
{
    /**
     * Login with email and password
     *
     * @OA\Post(
     *   path="/api/v1/auth/login",
     *   tags={"Auth"},
     *   summary="Authenticate user and issue JWT",
     *   requestBody=@OA\RequestBody(
     *     request="LoginRequest",
     *     required=true,
     *     content={@OA\MediaType(
     *       mediaType="application/json",
     *
     *       @OA\Schema(
     *         type="object",
     *         required={"email","password"},
     *
     *         @OA\Property(property="email", type="string", format="email", example="admin@example.com"),
     *         @OA\Property(property="password", type="string", format="password", example="a1b2c3d4e5")
     *       )
     *     )}
     *   ),
     *
     *   @OA\Response(
     *     response=200,
     *     description="Successful login",
     *     content={@OA\MediaType(
     *       mediaType="application/json",
     *
     *       @OA\Schema(
     *         type="object",
     *
     *         @OA\Property(property="access_token", type="string"),
     *         @OA\Property(property="token_type", type="string", example="bearer"),
     *         @OA\Property(property="expires_in", type="integer", example=3600)
     *       )
     *     )}
     *   ),
     *
     *   @OA\Response(response=401, description="Invalid credentials")
     * )
     */
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

    /**
     * Get the authenticated user
     *
     * @OA\Get(
     *   path="/api/v1/auth/me",
     *   tags={"Auth"},
     *   summary="Retrieve authenticated user",
     *   security={{"bearerAuth": {}}},
     *
     *   @OA\Response(
     *     response=200,
     *     description="Authenticated user",
     *     content={@OA\MediaType(
     *       mediaType="application/json",
     *
     *       @OA\Schema(
     *         type="object",
     *
     *         @OA\Property(property="id", type="integer", example=1),
     *         @OA\Property(property="name", type="string", example="Admin"),
     *         @OA\Property(property="email", type="string", format="email", example="admin@example.com")
     *       )
     *     )}
     *   ),
     *
     *   @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function me(): JsonResponse
    {
        return response()->json(Auth::guard('api')->user());
    }

    /**
     * Logout the authenticated user
     *
     * @OA\Post(
     *   path="/api/v1/auth/logout",
     *   tags={"Auth"},
     *   summary="Logout and invalidate current token",
     *   security={{"bearerAuth": {}}},
     *
     *   @OA\Response(
     *     response=200,
     *     description="Logged out",
     *     content={@OA\MediaType(
     *       mediaType="application/json",
     *
     *       @OA\Schema(
     *         type="object",
     *
     *         @OA\Property(property="message", type="string", example="Logged out")
     *       )
     *     )}
     *   ),
     *
     *   @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function logout(): JsonResponse
    {
        Auth::guard('api')->logout();

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Refresh the JWT token
     *
     * @OA\Post(
     *   path="/api/v1/auth/refresh",
     *   tags={"Auth"},
     *   summary="Refresh JWT",
     *   security={{"bearerAuth": {}}},
     *
     *   @OA\Response(
     *     response=200,
     *     description="New token issued",
     *     content={@OA\MediaType(
     *       mediaType="application/json",
     *
     *       @OA\Schema(
     *         type="object",
     *
     *         @OA\Property(property="access_token", type="string"),
     *         @OA\Property(property="token_type", type="string", example="bearer"),
     *         @OA\Property(property="expires_in", type="integer", example=3600)
     *       )
     *     )}
     *   ),
     *
     *   @OA\Response(response=401, description="Token refresh failed")
     * )
     */
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
