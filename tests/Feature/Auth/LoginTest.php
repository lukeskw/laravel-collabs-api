<?php

use App\Models\User;

it('authenticates user with valid credentials', function (): void {
    $password = 'a1b2c3d4';

    $user = User::factory()->create([
        'password' => $password,
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => $password,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);
});

it('rejects invalid credentials', function (): void {
    $user = User::factory()->create([
        'password' => 'valid-password',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'invalid-password',
    ]);

    $response->assertUnauthorized()
        ->assertJson([
            'message' => 'Invalid credentials',
        ]);
});
