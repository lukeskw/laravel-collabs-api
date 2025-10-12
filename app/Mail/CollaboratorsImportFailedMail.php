<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CollaboratorsImportFailedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $fileName,
        public readonly ?string $errorMessage = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Falha na importação de colaboradores',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.collaborators.import_failed',
            with: [
                'fileName' => $this->fileName,
                'errorMessage' => $this->errorMessage,
            ],
        );
    }
}
