<?php

use App\Mail\CollaboratorsImportedMail;
use App\Models\Collaborator;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

it('lists only collaborators belonging to the authenticated manager', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Collaborator::factory()->for($user)->count(2)->create();
    Collaborator::factory()->for($otherUser)->create();

    $response = $this->getJson('/api/v1/collaborators', headers: apiHeaders($user));

    $response->assertOk();
    $response->assertJsonCount(2, 'data');
});

it('creates a collaborator for the authenticated manager', function (): void {
    $user = User::factory()->create();

    $payload = [
        'name' => 'John Doe',
        'email' => 'JOHN.DOE@example.com',
        'cpf' => '123.456.789-01',
        'city' => 'São Paulo',
        'state' => 'SP',
    ];

    $response = $this->postJson('/api/v1/collaborators', $payload, headers: apiHeaders($user));

    $response->assertCreated();
    $response->assertJsonPath('data.name', 'John Doe');
    $response->assertJsonPath('data.email', 'john.doe@example.com');
    $response->assertJsonPath('data.cpf', '12345678901');
    $response->assertJsonPath('data.cpfFormatted', '123.456.789-01');

    $this->assertDatabaseHas('collaborators', [
        'user_id' => $user->id,
        'email' => 'john.doe@example.com',
        'cpf' => '12345678901',
    ]);
});

it('updates collaborator data for the authenticated manager', function (): void {
    $user = User::factory()->create();
    $collaborator = Collaborator::factory()->for($user)->create([
        'cpf' => '12345678901',
    ]);

    $payload = [
        'name' => 'Jane Doe',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
    ];

    $response = $this->putJson(
        sprintf('/api/v1/collaborators/%s', $collaborator->id),
        $payload,
        headers: apiHeaders($user)
    );

    $response->assertOk();
    $response->assertJsonPath('data.name', 'Jane Doe');
    $response->assertJsonPath('data.city', 'Rio de Janeiro');

    $this->assertDatabaseHas('collaborators', [
        'id' => $collaborator->id,
        'name' => 'Jane Doe',
        'city' => 'Rio de Janeiro',
        'state' => 'RJ',
    ]);
});

it('prevents a manager from updating collaborators from another manager', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $collaborator = Collaborator::factory()->for($otherUser)->create();

    $response = $this->putJson(
        sprintf('/api/v1/collaborators/%s', $collaborator->id),
        ['name' => 'Hacker'],
        headers: apiHeaders($user)
    );

    $response->assertForbidden();
});

it('deletes a collaborator belonging to the authenticated manager', function (): void {
    $user = User::factory()->create();
    $collaborator = Collaborator::factory()->for($user)->create();

    $response = $this->deleteJson(
        sprintf('/api/v1/collaborators/%s', $collaborator->id),
        headers: apiHeaders($user)
    );

    $response->assertNoContent();

    $this->assertDatabaseMissing('collaborators', [
        'id' => $collaborator->id,
    ]);
});

it('imports collaborators from CSV and notifies the manager', function (): void {
    Mail::fake();
    Storage::fake('local');

    $user = User::factory()->create();

    $csv = implode("\n", [
        'name,email,cpf,city,state',
        'Fulano,FULANO@example.com,987.654.321-00,São Paulo,SP',
        'Ciclano,ciclano@example.com,12345678901,Rio de Janeiro,RJ',
        ',invalid@example.com,00000000000,Invalid,XX',
    ]);

    $file = UploadedFile::fake()->createWithContent('collaborators.csv', $csv);

    $response = $this->postJson(
        '/api/v1/collaborators/import',
        ['file' => $file],
        headers: apiHeaders($user)
    );

    $response->assertAccepted();
    $response->assertJsonPath('message', trans('general.import_started'));

    $this->assertDatabaseHas('collaborators', [
        'user_id' => $user->id,
        'email' => 'fulano@example.com',
        'cpf' => '98765432100',
    ]);

    $this->assertDatabaseHas('collaborators', [
        'user_id' => $user->id,
        'email' => 'ciclano@example.com',
        'cpf' => '12345678901',
    ]);

    Mail::assertQueued(CollaboratorsImportedMail::class, static function (CollaboratorsImportedMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && $mail->result->created === 2
            && $mail->result->skipped === 1;
    });
});
