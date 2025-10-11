<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

uses(TestCase::class, RefreshDatabase::class)->in('Feature');
uses(TestCase::class)->in('Unit');

/**
 * @return array<string, string>
 */
function apiHeaders(User $user): array
{
    $token = JWTAuth::fromUser($user);

    return [
        'Authorization' => sprintf('Bearer %s', $token),
        'Accept' => 'application/json',
    ];
}
