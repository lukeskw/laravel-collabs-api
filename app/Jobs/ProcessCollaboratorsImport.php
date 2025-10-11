<?php

namespace App\Jobs;

use App\Contracts\CollaboratorsImporterContract;
use App\DataTransferObjects\CollaboratorsImportResult;
use App\Mail\CollaboratorsImportedMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessCollaboratorsImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly string $path,
        private readonly string $disk = 'local'
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function handle(CollaboratorsImporterContract $importer): CollaboratorsImportResult
    {
        $user = User::query()->findOrFail($this->userId);
        $result = null;
        $shouldDeleteFile = false;

        try {
            $result = $importer->import($user->id, $this->path, $this->disk);
            Mail::to($user)->queue(new CollaboratorsImportedMail($result));

            $shouldDeleteFile = true;
            $this->deleteFile();
            $shouldDeleteFile = false;
        } catch (Throwable $exception) {
            if ($shouldDeleteFile) {
                $this->deleteFile();
            }

            throw $exception;
        }

        return $result;
    }

    public function failed(): void
    {
        // TODO: enviar email de falha
        $this->deleteFile();
    }

    private function deleteFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
    }
}
