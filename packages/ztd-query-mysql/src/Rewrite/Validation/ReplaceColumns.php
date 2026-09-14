<?php

declare(strict_types=1);

namespace ZtdQuery\Platform\MySql\Rewrite\Validation;

use PhpMyAdmin\SqlParser\Statements\ReplaceStatement;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Schema\TableDefinitionRegistry;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Replace Columns.
 *
 * @visibility ZtdQuery\Platform\MySql
 */
final class ReplaceColumns
{
    /**
     * Configure the dependencies used by this operation.
     */
    public function __construct(private TableDefinitionRegistry $registry, private ShadowStore $shadowStore)
    {
    }
    /**
     * Ensure REPLACE has columns available.
     * @throws UnsupportedSqlException
     */
    public function ensureReplaceColumns(ReplaceStatement $statement, string $sql): void
    {
        $tableName = self::resolveIntoTableName($statement->into);
        if ($tableName === null) {
            return;
        }

        $columns = $statement->into->columns ?? [];
        $columns = array_values(array_filter($columns, 'is_string'));
        if ($columns !== []) {
            return;
        }

        $rows = $this->shadowStore->get($tableName);
        if ($rows !== []) {
            return;
        }

        $definition = $this->registry->get($tableName);
        if ($definition !== null) {
            return;
        }

        throw new UnsupportedSqlException($sql, 'Cannot determine columns');
    }

    /**
     * Resolve table name from an INTO clause.
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
