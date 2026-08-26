<?php

declare(strict_types=1);

namespace Tests\Fake;

use ZtdQuery\Platform\CopySupport;
use ZtdQuery\Platform\CopyTarget;
use ZtdQuery\Schema\TableDefinition;

/**
 * A COPY implementation writing the plainest form of every part of it.
 *
 * COPY is PostgreSQL's, and how a relation, a column list and a row of values
 * are written is its business. What every implementation has to do is the
 * same: say which table is meant, which columns, how a row is written out and
 * how one is read back. That is what this shows.
 */
final class FakeCopySupport implements CopySupport
{
    /**
     * Answers the table a relation names, without its schema.
     *
     * @param string $relation Relation as the statement named it
     *
     * @return string The table name
     */
    public function tableName(string $relation): string
    {
        $parts = explode('.', trim($relation, '"'));

        return $parts[count($parts) - 1];
    }

    /**
     * Answers what a COPY statement is written against.
     *
     * @param string $relation Relation as the statement named it
     * @param string|null $fields Column list as the statement wrote it, or null for every column
     * @param TableDefinition $definition What the table declares
     *
     * @return CopyTarget The relation and the columns
     */
    public function target(string $relation, ?string $fields, TableDefinition $definition): CopyTarget
    {
        $columns = $fields === null
            ? $definition->columns
            : array_map(trim(...), explode(',', $fields));
        $relationParts = explode('.', trim($relation, '"'));

        return new CopyTarget(
            $relationParts === [] ? [$relation] : $relationParts,
            $columns === [] ? ['*'] : array_values($columns),
        );
    }

    /**
     * Writes the SELECT that reads the rows a COPY OUT would have written.
     *
     * @param CopyTarget $target What the statement is written against
     *
     * @return string The statement
     */
    public function selectSql(CopyTarget $target): string
    {
        return 'SELECT ' . implode(', ', $target->columns) . ' FROM ' . $target->tableName();
    }

    /**
     * Writes the INSERT that would have written the rows a COPY IN carries.
     *
     * @param CopyTarget $target What the statement is written against
     * @param int $rowCount How many rows are being written
     * @param bool $overrideSystemValue Whether a column the database numbers is being written anyway
     *
     * @return string The statement
     */
    public function insertSql(CopyTarget $target, int $rowCount, bool $overrideSystemValue): string
    {
        $placeholders = '(' . implode(', ', array_fill(0, count($target->columns), '?')) . ')';

        return 'INSERT INTO ' . $target->tableName()
            . ' (' . implode(', ', $target->columns) . ')'
            . ($overrideSystemValue ? ' OVERRIDING SYSTEM VALUE' : '')
            . ' VALUES ' . implode(', ', array_fill(0, max($rowCount, 1), $placeholders));
    }

    /**
     * Writes one row the way COPY carries it.
     *
     * @param list<mixed> $values Values of the row, in column order
     * @param string $separator What goes between two values
     * @param string $nullAs What a null is written as
     *
     * @return string The row
     */
    public function encodeRow(array $values, string $separator, string $nullAs): string
    {
        $written = [];
        foreach ($values as $value) {
            $written[] = $value === null ? $nullAs : (is_scalar($value) ? (string) $value : $nullAs);
        }

        return implode($separator, $written);
    }

    /**
     * Reads one row the way COPY carries it.
     *
     * @param string $row Row as it was written
     * @param string $separator What goes between two values
     * @param string $nullAs What a null is written as
     *
     * @return list<string|null> The values, in column order
     */
    public function decodeRow(string $row, string $separator, string $nullAs): array
    {
        $values = [];
        foreach (explode($separator, $row) as $written) {
            $values[] = $written === $nullAs ? null : $written;
        }

        return $values;
    }

    /**
     * Reports whether a statement is a COPY at all.
     *
     * @param string $sql Statement as it was written
     *
     * @return bool True when it is
     */
    public function isCopyStatement(string $sql): bool
    {
        return preg_match('/^\s*COPY\b/i', $sql) === 1;
    }
}
