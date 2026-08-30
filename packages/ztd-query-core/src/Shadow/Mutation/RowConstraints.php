<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Exception\DuplicateKeyException;
use ZtdQuery\Exception\NotNullViolationException;
use ZtdQuery\Schema\TableDefinition;

/**
 * Refuses a row the table would not have accepted.
 *
 * Nothing is inserted, so nothing checks these but this. A statement whose
 * rows the database would have rejected has to be rejected here too, or the
 * shadow ends up describing a state the database could never have been in.
 *
 * A key with a null in it is left alone: SQL says such a key collides with
 * nothing, not even with another row carrying the same nulls.
 *
 * @phpstan-import-type Row from TableDefinition
 * @phpstan-import-type RowValue from TableDefinition
 */
final class RowConstraints
{
    /**
     * @param TableDefinition|null $definition What the table declares, or null when nothing has described it
     * @param string $tableName Table being written to, for the refusal
     * @param string $sql Statement being simulated, for the refusal
     */
    public function __construct(
        private readonly ?TableDefinition $definition,
        private readonly string $tableName,
        private readonly string $sql,
    ) {
    }

    /**
     * Refuses a row that leaves null in a column the table will not take one in.
     *
     * A column the row does not carry at all is not a null: the database would
     * put its default there.
     *
     * @param Row $row Row that would be written
     *
     * @throws NotNullViolationException When a column that cannot be null is written as null
     */
    public function assertNoNullWhereNoneIsAllowed(array $row): void
    {
        if ($this->definition === null) {
            return;
        }

        foreach ($this->definition->notNullColumns as $columnName) {
            if (array_key_exists($columnName, $row) && $row[$columnName] === null) {
                throw new NotNullViolationException($this->sql, $this->tableName, $columnName);
            }
        }
    }

    /**
     * Refuses a row that would collide with one already there on a unique key.
     *
     * A row being changed does not collide with itself, so the row already
     * there that is this row is left out. Which columns say so is passed in,
     * because an insert has no such row and an update does.
     *
     * @param Row $row Row that would be written
     * @param list<Row> $existingRows Rows already there
     * @param list<string> $identityColumns Columns that say a row already there is this row
     *
     * @throws DuplicateKeyException When the row collides on one of the table's unique keys
     */
    public function assertNoDuplicateUniqueKey(array $row, array $existingRows, array $identityColumns = []): void
    {
        if ($this->definition === null) {
            return;
        }

        foreach ($this->definition->uniqueConstraints as $keyName => $columns) {
            if ($this->carriesNullIn($row, $columns)) {
                continue;
            }

            foreach ($existingRows as $existing) {
                if ($identityColumns !== [] && $this->agreeOn($row, $existing, $identityColumns)) {
                    continue;
                }
                if ($this->agreeOn($row, $existing, $columns)) {
                    throw new DuplicateKeyException(
                        $this->sql,
                        $this->tableName,
                        $keyName,
                        $this->keyValues($row, $columns),
                    );
                }
            }
        }
    }

    /**
     * Reports whether a row leaves any of the named columns unwritten or null.
     *
     * @param Row $row Row to read
     * @param list<string> $columns Columns of one key
     *
     * @return bool True when the key cannot collide with anything
     */
    public function carriesNullIn(array $row, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row) || $row[$column] === null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reports whether two rows carry the same values in the named columns.
     *
     * A row that does not carry one of them agrees with nothing on it.
     *
     * @param Row $row Row that would be written
     * @param Row $existing Row already there
     * @param list<string> $columns Columns of one key
     *
     * @return bool True when both carry every column identically
     */
    public function agreeOn(array $row, array $existing, array $columns): bool
    {
        foreach ($columns as $column) {
            if (!isset($existing[$column]) || ($row[$column] ?? null) !== $existing[$column]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reads the values a row carries on one key, under the column names.
     *
     * @param Row $row Row to read
     * @param list<string> $columns Columns of one key
     *
     * @return Row Column => the value it carries, or null where it carries none
     */
    public function keyValues(array $row, array $columns): array
    {
        $values = [];
        foreach ($columns as $column) {
            $values[$column] = $row[$column] ?? null;
        }

        return $values;
    }
}
