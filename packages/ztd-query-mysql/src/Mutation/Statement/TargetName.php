<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Mutation\Statement;

use PhpMyAdmin\SqlParser\Statements\InsertStatement;
use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;

/**
 * Target Name.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class TargetName
{
    /**
     * Resolve table name from an INTO clause (InsertStatement or ReplaceStatement).
     */
    public static function resolveIntoTableName(?\PhpMyAdmin\SqlParser\Components\IntoKeyword $into): ?string
    {
        if ($into === null || $into->dest === null) {
            return null;
        }
        $dest = $into->dest;
        return is_string($dest) ? $dest : ($dest->table ?? null);
    }
}
