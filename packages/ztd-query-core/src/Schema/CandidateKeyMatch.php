<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Reads what a row carries in a candidate key, and tells when another row repeats it.
 *
 * A candidate key only identifies a row when the row actually carries every
 * column of it and carries something in each. SQL says a key with a null part
 * collides with nothing, not even with itself, so a row that is missing a
 * column or holds null there has no key values at all — which is a different
 * answer from having key values that happen to be empty.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class CandidateKeyMatch
{
    /**
     * Reads what a row carries in the named columns.
     *
     * @param Row $row Row to read
     * @param array<int, string> $columns Columns the key is made of
     *
     * @return Row|null The values, keyed by column, or null when the row cannot be identified by this key
     */
    public function of(array $row, array $columns): ?array
    {
        if ($columns === []) {
            return null;
        }

        $values = [];
        foreach ($columns as $column) {
            if (!isset($row[$column])) {
                return null;
            }
            $values[$column] = $row[$column];
        }

        return $values;
    }

    /**
     * Reports whether a row repeats these key values.
     *
     * @param Row $values Key values another row was read as carrying
     * @param Row $row Row to test
     *
     * @return bool True when the row carries every one of them identically
     */
    public function carriedBy(array $values, array $row): bool
    {
        foreach ($values as $column => $value) {
            if (($row[$column] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }
}
