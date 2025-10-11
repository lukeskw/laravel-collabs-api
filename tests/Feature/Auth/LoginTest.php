<?php

use App\Models\User;

it('logs in with valid credentials and returns a JWT token', function (): void {
    $password = 'test-password-123';

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'password' => $password,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in']);

    expect($response->json('token_type'))->toBe('bearer');
    expect($response->json('access_token'))->toBeString()->not->toBeEmpty();
    expect($response->json('expires_in'))->toBeInt()->toBeGreaterThan(0);
});

it('rejects invalid login attempts', function (): void {
    $password = 'correct-password';

    User::factory()->create([
        'email' => 'user@example.com',
        'password' => $password,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'user@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401)
        ->assertJson(['message' => 'Invalid credentials']);
});

it('returns the authenticated user when providing a valid JWT', function (): void {
    $password = 'testing-12345';

    $user = User::factory()->create([
        'email' => 'user@example.com',
        'name' => 'Test User',
        'password' => $password,
    ]);

    $login = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $token = $login->json('access_token');

    expect($token)->not->toBeNull();

    $me = $this->getJson('/api/v1/auth/me', [
        'Authorization' => 'Bearer '.$token,
    ]);

    $me->assertOk()
        ->assertJson([
            'id' => $user->id,
            'name' => 'Test User',
            'email' => $user->email,
        ]);
});
