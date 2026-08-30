<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Row;

use ZtdQuery\Schema\TableDefinition;

/**
 * Finds a row among rows, by the columns that identify it.
 *
 * Nothing here knows about foreign keys or about mutations. It answers the one
 * question every part of the shadow keeps asking — is this the same row, and
 * where is it — so that the answer is given the same way everywhere.
 *
 * @phpstan-import-type Row from TableDefinition
 * @phpstan-import-type RowValue from TableDefinition
 */
final class RowMatch
{
    /**
     * Reads the values a row carries in the named columns.
     *
     * A row that does not carry one of them cannot be compared on them at all,
     * which is different from carrying null there, so it answers nothing rather
     * than a list with a hole in it.
     *
     * @param Row $row Row to read
     * @param list<string> $columns Columns to read, in order
     *
     * @return list<RowValue>|null The values, or null when the row lacks one of the columns
     */
    public function valuesOf(array $row, array $columns): ?array
    {
        $values = [];
        foreach ($columns as $column) {
            if (!array_key_exists($column, $row)) {
                return null;
            }
            $values[] = $row[$column];
        }

        return $values;
    }

    /**
     * Reports whether a row carries exactly these values in these columns.
     *
     * @param Row $row Row to test
     * @param list<string> $columns Columns to test, in order
     * @param list<RowValue> $values Values they must carry, in the same order
     *
     * @return bool True when every column carries the value paired with it
     */
    public function carries(array $row, array $columns, array $values): bool
    {
        foreach ($columns as $index => $column) {
            if (!array_key_exists($column, $row) || $row[$column] !== ($values[$index] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reports whether two rows agree on the named columns.
     *
     * @param Row $left One row
     * @param Row $right The other
     * @param list<string> $keys Columns they must agree on
     *
     * @return bool True when both carry every key and carry it identically
     */
    public function agreeOn(array $left, array $right, array $keys): bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $left)
                || !array_key_exists($key, $right)
                || $left[$key] !== $right[$key]
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Reports whether two rows carry the same columns with the same values.
     *
     * Column order is not part of a row's identity: the same columns carrying
     * the same values are the same row however the reader happened to order
     * them.
     *
     * @param Row $left One row
     * @param Row $right The other
     *
     * @return bool True when neither carries a column the other lacks, and both agree everywhere
     */
    public function sameRow(array $left, array $right): bool
    {
        if (count($left) !== count($right)) {
            return false;
        }

        return $this->agreeOn($left, $right, array_keys($left));
    }

    /**
     * Reports whether two rows are the same row of a table keyed like this.
     *
     * A table with a key is identified by it, and two rows agreeing on it are
     * the same row however else they differ. A table with no key has nothing
     * to be identified by, so being the same row means carrying everything the
     * same.
     *
     * @param Row $left One row
     * @param Row $right The other
     * @param array<int, string> $keys Columns that identify a row, or none where the table declares none
     *
     * @return bool True when the table cannot tell the two apart
     */
    public function identifies(array $left, array $right, array $keys): bool
    {
        if ($keys === []) {
            return $this->sameRow($left, $right);
        }

        return $this->agreeOn($left, $right, array_values($keys));
    }

    /**
     * Answers where a row with the same key is, among rows not already taken.
     *
     * Rows already paired off are excluded so that two rows sharing a key are
     * paired one to one rather than both to the first match.
     *
     * @param list<Row> $rows Rows to search
     * @param Row $candidate Row to look for
     * @param list<string> $keys Columns that identify a row
     * @param list<int> $excluded Positions already paired off
     *
     * @return int|null Position of the match, or null when there is none left
     */
    public function positionOfSameKey(array $rows, array $candidate, array $keys, array $excluded): ?int
    {
        foreach ($rows as $index => $row) {
            if (!in_array($index, $excluded, true) && $this->agreeOn($row, $candidate, $keys)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Answers where an identical row is, among rows not already taken.
     *
     * A table with no key has nothing to be identified by, so the whole row is
     * what has to match.
     *
     * @param list<Row> $rows Rows to search
     * @param Row $candidate Row to look for
     * @param list<int> $excluded Positions already paired off
     *
     * @return int|null Position of the match, or null when there is none left
     */
    public function positionOfIdentical(array $rows, array $candidate, array $excluded): ?int
    {
        foreach ($rows as $index => $row) {
            if (!in_array($index, $excluded, true) && $row === $candidate) {
                return $index;
            }
        }

        return null;
    }
}
