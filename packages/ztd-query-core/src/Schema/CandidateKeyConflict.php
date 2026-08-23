<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Identifies the existing row and candidate key responsible for a conflict.
 */
final class CandidateKeyConflict
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(
        public readonly int $rowIndex,
        public readonly string $keyName,
        public readonly array $values,
    ) {
    }
}
