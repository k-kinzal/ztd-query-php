<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

use PhpMyAdmin\SqlParser\Statement;

/**
 * SQL dialect abstraction for parsing and emitting statements.
 */
interface SqlDialect
{
    /**
     * Parse SQL into statements.
     *
     * @return array<int, Statement>
     */
    public function parse(string $sql): array;

    /**
     * Emit SQL from a parsed statement.
     */
    public function emit(Statement $statement): string;
}
