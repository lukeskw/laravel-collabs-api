<?php

namespace App\Services;

use App\Contracts\CollaboratorServiceContract;
use App\Contracts\CollaboratorsImporterContract;
use App\DataTransferObjects\CollaboratorsImportResult;
use App\Models\Collaborator;
use App\ValueObjects\BrazilianDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

// Pensei em usar um pacote pra ler CSV, mas como o formato é simples e o PHP tem suporte nativo, achei desnecessário
class CollaboratorsCsvImporter implements CollaboratorsImporterContract
{
    /**
     * @var list<string>
     */
    private const REQUIRED_COLUMNS = [
        'name',
        'email',
        'cpf',
        'city',
        'state',
    ];

    public function __construct(
        private readonly CollaboratorServiceContract $collaboratorService
    ) {}

    public function import(int $userId, string $path, string $disk = 'local'): CollaboratorsImportResult
    {
        $filesystem = Storage::disk($disk);
        // usando stream aqui pra não carregar o csv inteiro na memória
        $handle = $filesystem->readStream($path);

        if (! is_resource($handle)) {
            throw new RuntimeException('Unable to open provided file for reading.');
        }

        $results = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
        ];

        try {
            $header = $this->readHeader($handle);

            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                $record = $this->validateRow($header, $row);

                if ($record === null) {
                    $results['skipped']++;

                    continue;
                }

                $document = BrazilianDocument::from($record['cpf']);

                $payload = [
                    'name' => $record['name'],
                    'email' => strtolower($record['email']),
                    'cpf' => $document,
                    'city' => $record['city'],
                    'state' => $record['state'],
                ];

                $operation = DB::transaction(function () use ($userId, $document, $payload): string {
                    $collaborator = Collaborator::query()
                        ->forUserId($userId)
                        ->where('cpf', $document->value())
                        // usando lockForUpdate pra evitar race conditions aqui, o que é race condition? Ref: https://laravel.com/docs/12.x/queries#pessimistic-locking
                        // basicamente, se duas requisições tentarem criar o mesmo colaborador ao mesmo tempo, uma delas vai esperar a outra terminar a transação antes de continuar
                        // isso evita que duas linhas com o mesmo CPF sejam criadas para o mesmo usuário
                        ->lockForUpdate()
                        ->first();

                    if ($collaborator === null) {
                        $this->collaboratorService->createForUserId($userId, $payload);

                        return 'created';
                    }

                    $this->collaboratorService->updateForUserId($userId, $collaborator, $payload);

                    return 'updated';
                });

                if (array_key_exists($operation, $results)) {
                    $results[$operation]++;
                }
            }
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        return new CollaboratorsImportResult(
            created: $results['created'],
            updated: $results['updated'],
            skipped: $results['skipped'],
        );
    }

    /**
     * @param  resource  $handle
     * @return list<string>
     */
    private function readHeader($handle): array
    {
        $header = fgetcsv($handle, 0, ',');

        if ($header === false) {
            throw new RuntimeException('CSV file is empty.');
        }

        $header = array_map(static function (?string $column): string {
            $columnValue = $column ?? '';

            return strtolower(trim($columnValue));
        }, $header);

        if (array_diff(self::REQUIRED_COLUMNS, $header) !== []) {
            throw new RuntimeException('CSV header is missing required columns.');
        }

        return $header;
    }

    /**
     * @param  list<string>  $header
     * @param  list<string|null>  $row
     * @return array<string, string>|null
     */
    private function validateRow(array $header, array $row): ?array
    {
        if (count($header) !== count($row)) {
            return null;
        }

        $record = array_map(
            static function (?string $value): string {
                $value ??= '';

                return trim($value);
            },
            array_combine($header, $row) ?: []
        );

        if ($record === []) {
            return null;
        }

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (! isset($record[$column]) || $record[$column] === '') {
                return null;
            }
        }

        return $record;
    }
}
