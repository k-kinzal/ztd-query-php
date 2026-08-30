<?php

declare(strict_types=1);

namespace ZtdQuery\Schema\Key;

use ZtdQuery\Schema\TableDefinition;

/**
 * Identifies the existing row and candidate key responsible for a conflict.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class CandidateKeyConflict
{
    /**
     * @param Row $values
     */
    public function __construct(
        public readonly int $rowIndex,
        public readonly string $keyName,
        public readonly array $values,
    ) {
    }
}
