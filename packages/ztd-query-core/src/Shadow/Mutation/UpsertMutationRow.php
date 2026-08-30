<?php

declare(strict_types=1);

namespace ZtdQuery\Shadow\Mutation;

use ZtdQuery\Schema\TableDefinition;

/**
 * Names the columns an UPSERT carries its assignment values under.
 *
 * The rewritten statement works out, per row, whether the conflict clause
 * applied and what each assignment came to, and reads both back alongside the
 * row. Those columns are carried under names no table would use, and taken
 * off again before the row is handed to the caller.
 *
 * @phpstan-import-type Row from TableDefinition
 */
final class UpsertMutationRow
{
    private const VALUE_PREFIX = '__ztd_upsert_value_';

    private const PREDICATE = '__ztd_upsert_predicate';

    /**
     * Answers the name one assignment's value is carried under.
     *
     * @param int $index Position of the assignment in the conflict clause
     *
     * @return string Name no table would use
     */
    public function valueColumn(int $index): string
    {
        return self::VALUE_PREFIX . $index;
    }

    /**
     * Answers the name the conflict-clause verdict is carried under.
     *
     * @return string Name no table would use
     */
    public function predicateColumn(): string
    {
        return self::PREDICATE;
    }

    /**
     * Reports whether the verdict a driver read back means the clause applied.
     *
     * Every driver spells truth differently — a boolean, a one, the text of a
     * one, or Postgres\'s single t — and all four mean the same thing here.
     *
     * @param int|float|string|bool|null $value Verdict as the driver read it back
     *
     * @return bool True when the conflict clause applied
     */
    public function predicateMatches(int|float|string|bool|null $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't';
    }

    /**
     * Takes the carried columns off a row, leaving the row the statement wrote.
     *
     * @param Row $row Row as the rewritten statement read it back
     * @param int $valueCount How many assignments the conflict clause declares
     *
     * @return Row The row as the caller should see it
     */
    public function incomingRow(array $row, int $valueCount): array
    {
        for ($index = 0; $index < $valueCount; ++$index) {
            unset($row[$this->valueColumn($index)]);
        }
        unset($row[self::PREDICATE]);

        return $row;
    }
}
