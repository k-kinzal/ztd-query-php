<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Connection\StatementInterface;

/**
 * Names the columns a multi-table statement carries its per-table values under.
 *
 * One statement writing to several tables reads back as one result set, so
 * each table's values and its old key have to be carried under names no table
 * would use and taken apart again afterwards. The names are built from the
 * position of the table in the statement, which is the only thing both sides
 * agree on.
 *
 * @phpstan-import-type Row from StatementInterface
 */
final class MultiTableMutationRow
{
    private const PREFIX = '__ztd_multi_';

    /**
     * Answers the name one table's value is carried under.
     *
     * @param int $targetIndex Position of the table in the statement
     * @param int $columnIndex Position of the column among that table's values
     *
     * @return string Name no table would use
     */
    public function valueColumn(int $targetIndex, int $columnIndex): string
    {
        return self::PREFIX . $targetIndex . '_value_' . $columnIndex;
    }

    /**
     * Answers the name one table's old key value is carried under.
     *
     * @param int $targetIndex Position of the table in the statement
     * @param int $primaryKeyIndex Position of the column among that table's key columns
     *
     * @return string Name no table would use
     */
    public function identityColumn(int $targetIndex, int $primaryKeyIndex): string
    {
        return self::PREFIX . $targetIndex . '_identity_' . $primaryKeyIndex;
    }

    /**
     * Reads one table's values off a result row, under the names it declares.
     *
     * @param Row $row Row as the rewritten statement read it back
     * @param int $targetIndex Position of the table in the statement
     * @param list<string> $columns That table's columns, in the order they were written
     *
     * @return Row|null Column => value, or null when the row carries no values for that table
     */
    public function values(array $row, int $targetIndex, array $columns): ?array
    {
        $values = [];
        foreach ($columns as $columnIndex => $column) {
            $metadataColumn = $this->valueColumn($targetIndex, $columnIndex);
            if (!array_key_exists($metadataColumn, $row)) {
                return null;
            }
            $values[$column] = $row[$metadataColumn];
        }

        return $values;
    }

    /**
     * Reads one table's old key off a result row, under the names it declares.
     *
     * @param Row $row Row as the rewritten statement read it back
     * @param int $targetIndex Position of the table in the statement
     * @param list<string> $primaryKeys That table's key columns, in the order they were written
     *
     * @return Row|null Key column => value, or null when the row carries no key for that table
     */
    public function identity(array $row, int $targetIndex, array $primaryKeys): ?array
    {
        $identity = [];
        foreach ($primaryKeys as $primaryKeyIndex => $primaryKey) {
            $metadataColumn = $this->identityColumn($targetIndex, $primaryKeyIndex);
            if (!array_key_exists($metadataColumn, $row)) {
                return null;
            }
            $identity[$primaryKey] = $row[$metadataColumn];
        }

        return $identity;
    }
}
