<?php

namespace App\DataTransferObjects;

final class CollaboratorsImportResult
{
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
    ) {}

    public function total(): int
    {
        return $this->created + $this->updated;
    }
}
