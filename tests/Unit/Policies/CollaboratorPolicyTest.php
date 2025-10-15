<?php

use App\Models\Collaborator;
use App\Models\User;
use App\Policies\CollaboratorPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->policy = new CollaboratorPolicy;
});

describe('viewAny', function (): void {
    it('allows authenticated users to view any collaborators', function (): void {
        $user = User::factory()->create();

        expect($this->policy->viewAny($user))->toBeTrue();
    });
});

describe('view', function (): void {
    it('allows user to view their own collaborator', function (): void {
        $user = User::factory()->create();
        $collaborator = Collaborator::factory()->for($user)->create();

        expect($this->policy->view($user, $collaborator))->toBeTrue();
    });

    it('denies user from viewing another users collaborator', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $collaborator = Collaborator::factory()->for($otherUser)->create();

        expect($this->policy->view($user, $collaborator))->toBeFalse();
    });
});

describe('create', function (): void {
    it('allows authenticated users to create collaborators', function (): void {
        $user = User::factory()->create();

        expect($this->policy->create($user))->toBeTrue();
    });
});

describe('update', function (): void {
    it('allows user to update their own collaborator', function (): void {
        $user = User::factory()->create();
        $collaborator = Collaborator::factory()->for($user)->create();

        expect($this->policy->update($user, $collaborator))->toBeTrue();
    });

    it('denies user from updating another users collaborator', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $collaborator = Collaborator::factory()->for($otherUser)->create();

        expect($this->policy->update($user, $collaborator))->toBeFalse();
    });
});

describe('delete', function (): void {
    it('allows user to delete their own collaborator', function (): void {
        $user = User::factory()->create();
        $collaborator = Collaborator::factory()->for($user)->create();

        expect($this->policy->delete($user, $collaborator))->toBeTrue();
    });

    it('denies user from deleting another users collaborator', function (): void {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $collaborator = Collaborator::factory()->for($otherUser)->create();

        expect($this->policy->delete($user, $collaborator))->toBeFalse();
    });
});
