<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Explain;

use MySqlMemory\Dictionary\TableDefinition;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Statement;

/**
 * The clustered primary-index scan of an unrestricted single-table InnoDB UPDATE.
 *
 * Changing a primary-key column requires a temporary row-identity list. Integer primary keys
 * have fixed key lengths; other key domains and restricted scans retain the general fallback.
 * Verified through EXPLAIN on MySQL 8.4.7.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain-output.html.
 *
 * @visibility MySqlMemory
 */
final class PrimaryScan
{
    /**
     * Answers access type, key name, key length and Extra for this scan, or null for another access.
     *
     * @return array{string, string, string, string|null}|null
     */
    public static function of(Statement $update, ?TableDefinition $table): ?array
    {
        if (!$update instanceof Update || $table === null || strcasecmp($table->engine, 'InnoDB') !== 0 || $update->where !== null || $update->limit !== null || $update->orderBy !== [] || count($update->tables) !== 1 || !$update->tables[0] instanceof TableReference) {
            return null;
        }
        $primary = $table->primaryKey();
        if ($primary === null) {
            return null;
        }
        $length = 0;
        $names = [];
        foreach ($primary->columns as $position) {
            $column = $table->columns[$position];
            $bytes = self::INTEGER_BYTES[$column->domain->field->name] ?? null;
            if ($bytes === null) {
                return null;
            }
            $length += $bytes;
            $names[] = strtolower($column->name);
        }
        $changed = array_filter($update->assignments, static fn ($assignment): bool => in_array(strtolower($assignment->column->name->value), $names, true));

        return ['index', $primary->name, (string) $length, $changed === [] ? null : 'Using temporary'];
    }

    /**
     * Integer key storage lengths, independent of their display widths.
     *
     * @var array<string, int>
     */
    public const INTEGER_BYTES = ['Tiny' => 1, 'Short' => 2, 'Int24' => 3, 'Long' => 4, 'LongLong' => 8];
}
