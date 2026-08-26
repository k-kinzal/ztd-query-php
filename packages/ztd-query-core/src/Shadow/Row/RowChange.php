<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Row;

use ZtdQuery\Connection\StatementInterface;

/**
 * One row as it was, and as it became.
 *
 * A cascade has to know both: which row to look for on the child side is
 * decided by the old values, and what to write there by the new ones.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class RowChange
{
    /**
     * @param Row $before The row as it was
     * @param Row $after The row as it became
     */
    public function __construct(
        public readonly array $before,
        public readonly array $after,
    ) {
    }
}
