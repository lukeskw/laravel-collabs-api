<?php

namespace App\Contracts;

use App\DataTransferObjects\CollaboratorsImportResult;

interface CollaboratorsImporterContract
{
    public function import(int $userId, string $path, string $disk = 'local'): CollaboratorsImportResult;
}
