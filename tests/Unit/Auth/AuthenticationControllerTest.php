<?php

use App\Api\v1\Http\Controllers\AuthenticationController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

// Já tinha esse arquivo em outro projeto, ele serve pra bater 100% de coverage na AuthenticationController

it('logs out the api guard and returns a confirmation message', function (): void {
    $guard = Mockery::mock();
    $guard->shouldReceive('logout')->once();

    Auth::shouldReceive('guard')
        ->once()
        ->with('api')
        ->andReturn($guard);

    $controller = new AuthenticationController;

    $response = $controller->logout();

    expect($response)->toBeInstanceOf(JsonResponse::class);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toMatchArray(['message' => 'Logged out']);
});

it('returns the authenticated user payload from the api guard', function (): void {
    $userPayload = ['id' => 1, 'name' => 'Unit User', 'email' => 'unit@example.com'];

    $guard = Mockery::mock();
    $guard->shouldReceive('user')->once()->andReturn($userPayload);

    Auth::shouldReceive('guard')
        ->once()
        ->with('api')
        ->andReturn($guard);

    $controller = new AuthenticationController;

    $response = $controller->me();

    expect($response->getStatusCode())->toBe(200);
    expect($response->getData(true))->toBe($userPayload);
});

it('refreshes the jwt token and returns the response payload', function (): void {
    Config::set('jwt.ttl', 15);

    $parseToken = Mockery::mock();
    $parseToken->shouldReceive('refresh')->once()->andReturn('new-jwt-token');

    JWTAuth::shouldReceive('parseToken')
        ->once()
        ->andReturn($parseToken);

    $controller = new AuthenticationController;

    $response = $controller->refresh();

    expect($response->getStatusCode())->toBe(200);

    $data = $response->getData(true);

    expect($data)->toMatchArray([
        'access_token' => 'new-jwt-token',
        'token_type' => 'bearer',
    ]);

    expect($data['expires_in'])->toBe(15 * 60);
});

it('handles refresh failure gracefully when JWT cannot be refreshed', function (): void {
    Config::set('jwt.ttl', 10);

    $parseToken = Mockery::mock();
    $parseToken->shouldReceive('refresh')
        ->once()
        ->andThrow(new JWTException('invalid token'));

    JWTAuth::shouldReceive('parseToken')
        ->once()
        ->andReturn($parseToken);

    $controller = new AuthenticationController;

    $response = $controller->refresh();

    expect($response->getStatusCode())->toBe(401);
    expect($response->getData(true))->toMatchArray([
        'message' => 'Token refresh failed',
    ]);
});

it('falls back to default ttl when jwt ttl config is not numeric', function (): void {
    Config::set('jwt.ttl', 'invalid');

    $controller = new AuthenticationController;

    $reflector = new ReflectionClass($controller);
    $method = $reflector->getMethod('respondWithToken');
    $method->setAccessible(true);

    $response = $method->invoke($controller, 'token-value');

    expect($response)->toBeInstanceOf(JsonResponse::class);

    $data = $response->getData(true);

    expect($data['expires_in'])->toBe(60 * 60);
    expect($data['access_token'])->toBe('token-value');
});
