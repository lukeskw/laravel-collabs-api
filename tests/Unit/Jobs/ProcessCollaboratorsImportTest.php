<?php

use App\Contracts\CollaboratorsImporterContract;
use App\DataTransferObjects\CollaboratorsImportResult;
use App\Jobs\ProcessCollaboratorsImport;
use App\Mail\CollaboratorsImportedMail;
use App\Mail\CollaboratorsImportFailedMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('handles successful collaborator imports and cleans up resources', function (): void {
    Mail::fake();
    Log::spy();
    Storage::fake('local');

    $user = User::factory()->create();
    $path = 'imports/collaborators.csv';

    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
    $disk = Storage::disk('local');
    $disk->put($path, 'name,email,cpf');

    $importer = new class implements CollaboratorsImporterContract
    {
        public function import(int $userId, string $path, string $disk = 'local'): CollaboratorsImportResult
        {
            return new CollaboratorsImportResult(created: 2, updated: 1, skipped: 0);
        }
    };

    $job = new ProcessCollaboratorsImport($user->id, $path);

    $result = $job->handle($importer);

    expect($result->created)->toBe(2)
        ->and($result->updated)->toBe(1);

    Mail::assertQueued(CollaboratorsImportedMail::class, static function (CollaboratorsImportedMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && $mail->result->created === 2
            && $mail->result->updated === 1;
    });

    $disk->assertMissing($path);

    Log::shouldHaveReceived('info')
        ->once()
        ->withArgs(function (string $message, array $context) use ($user, $result): bool {
            return $message === 'Collaborators import completed'
                && $context['user_id'] === $user->id
                && $context['imported'] === $result;
        });
});

it('queues failure notification and removes the source file on failure', function (): void {
    Mail::fake();
    Log::spy();
    Storage::fake('local');

    $user = User::factory()->create();
    $path = 'imports/failure.csv';

    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
    $disk = Storage::disk('local');
    $disk->put($path, 'csv');

    $job = new ProcessCollaboratorsImport($user->id, $path);

    $exception = new RuntimeException('Import failed');

    $job->failed($exception);

    Mail::assertQueued(CollaboratorsImportFailedMail::class, static function (CollaboratorsImportFailedMail $mail) use ($user): bool {
        return $mail->hasTo($user->email)
            && $mail->fileName === 'failure.csv'
            && $mail->errorMessage === trans('general.import_failed');
    });

    $disk->assertMissing($path);

    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(function (string $message, array $context) use ($user, $exception, $path): bool {
            return $message === 'Collaborators import failed'
                && $context['user_id'] === $user->id
                && $context['error'] === $exception->getMessage()
                && $context['path'] === $path;
        });
});
