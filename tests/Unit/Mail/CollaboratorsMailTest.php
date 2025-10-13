<?php

use App\DataTransferObjects\CollaboratorsImportResult;
use App\Mail\CollaboratorsImportedMail;
use App\Mail\CollaboratorsImportFailedMail;
use Illuminate\Contracts\Queue\ShouldQueue;

it('builds the imported collaborators mail with result data', function (): void {
    $result = new CollaboratorsImportResult(created: 2, updated: 1, skipped: 3);
    $mail = new CollaboratorsImportedMail($result);

    expect($mail)->toBeInstanceOf(ShouldQueue::class);

    $envelope = $mail->envelope();
    $content = $mail->content();

    expect($envelope->subject)->toBe('Colaboradores importados')
        ->and($content->markdown)->toBe('emails.collaborators.imported')
        ->and($content->with)->toMatchArray([
            'messageText' => 'Processamento realizado com sucesso',
            'result' => $result,
        ]);
});

it('builds the failed collaborators import mail with context details', function (): void {
    $mail = new CollaboratorsImportFailedMail('import.csv', 'Network error');

    expect($mail)->toBeInstanceOf(ShouldQueue::class);

    $envelope = $mail->envelope();
    $content = $mail->content();

    expect($envelope->subject)->toBe('Falha na importação de colaboradores')
        ->and($content->markdown)->toBe('emails.collaborators.import_failed')
        ->and($content->with)->toMatchArray([
            'fileName' => 'import.csv',
            'errorMessage' => 'Network error',
        ]);
});
