<?php

namespace App\Mail;

use App\DataTransferObjects\CollaboratorsImportResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CollaboratorsImportedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly CollaboratorsImportResult $result
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Colaboradores importados',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.collaborators.imported',
            with: [
                'messageText' => 'Processamento realizado com sucesso',
                'result' => $this->result,
            ],
        );
    }
}
