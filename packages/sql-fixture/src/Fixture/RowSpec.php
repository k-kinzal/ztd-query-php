<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use SqlFixture\InvalidOverrideException;
use SqlFixture\TypeMapper\TypeMapperInterface;

/**
 * What the caller asked for, for one table.
 *
 * The second argument of fixture() only overrides what would otherwise be
 * generated, so a table may be described four ways:
 *
 *     3                        three rows, every column generated
 *     ['status' => 'paid']     every row has that status, count still free
 *     [[...], [...]]           one entry per row
 *     []                       no rows at all
 *
 * An absent table is not described at all, and everything about it, count
 * included, is generated.
 *
 * @phpstan-import-type FixtureOverride from TypeMapperInterface
 * @phpstan-import-type FixtureRow from TypeMapperInterface
 * @phpstan-import-type FixtureValue from TypeMapperInterface
 */
final class RowSpec
{
    /**
     * @param int|null $count How many rows the caller asked for, or null when the count is free
     * @param list<FixtureRow>|null $rows Overrides per row, or null when the caller described the rows together
     * @param FixtureRow $sharedOverrides Overrides every row of the table carries
     */
    public function __construct(
        public readonly ?int $count,
        private readonly ?array $rows,
        private readonly array $sharedOverrides,
    ) {
    }

    /**
     * Answers the description of a table the caller said nothing about.
     *
     * @return self A description that fixes nothing
     */
    public static function unspecified(): self
    {
        return new self(null, null, []);
    }

    /**
     * Reads what the caller wrote for one table.
     *
     * @param string $table Table being described, for the error message
     * @param int|array<mixed>|TableOverrides $spec What the caller wrote
     *
     * @return self The description it stands for
     *
     * @throws InvalidOverrideException When a row count is negative
     */
    public static function from(string $table, int|array|TableOverrides $spec): self
    {
        if (is_int($spec)) {
            if ($spec < 0) {
                throw InvalidOverrideException::negativeRowCount($table, $spec);
            }

            return new self($spec, null, []);
        }

        if ($spec instanceof TableOverrides) {
            return new self(null, null, $spec->toArray());
        }

        $rows = self::asRows($spec);
        if ($rows !== null) {
            return new self(count($rows), $rows, []);
        }

        return new self(null, null, self::asRow($spec));
    }

    /**
     * Answers the overrides one row carries.
     *
     * @param int $index Which row of the table
     *
     * @return FixtureRow Column name => the value that row must carry
     */
    public function overridesFor(int $index): array
    {
        if ($this->rows === null) {
            return $this->sharedOverrides;
        }

        return $this->rows[$index] ?? [];
    }

    /**
     * Reads what the caller wrote as one entry per row, where that is what it is.
     *
     * A list of arrays describes the rows one at a time; anything else is one
     * set of column values that every row shares. The two are told apart by
     * shape alone, because both are written as arrays.
     *
     * @param array<mixed> $spec What the caller wrote
     *
     * @return list<FixtureRow>|null One entry per row, or null when the rows were described together
     */
    public static function asRows(array $spec): ?array
    {
        if (!array_is_list($spec)) {
            return null;
        }

        $rows = [];
        foreach ($spec as $entry) {
            if ($entry instanceof TableOverrides) {
                $rows[] = $entry->toArray();
                continue;
            }

            if (!is_array($entry)) {
                return null;
            }

            $rows[] = self::asRow($entry);
        }

        return $rows;
    }

    /**
     * Reads what the caller wrote for every table they described.
     *
     * @param array<string, int|array<mixed>|TableOverrides> $overrides Table name => what the caller wrote
     *
     * @return array<string, self> Table name => the description it stands for
     *
     * @throws InvalidOverrideException When a row count is negative
     */
    public static function forTables(array $overrides): array
    {
        $specs = [];
        foreach ($overrides as $table => $spec) {
            $specs[$table] = self::from($table, $spec);
        }

        return $specs;
    }

    /**
     * Reads what a caller wrote for one row as values a driver can bind.
     *
     * This is the boundary between arbitrary caller input and a fixture row.
     * A column name is a string and a column value is a scalar or null, so
     * anything else was never going to reach the database and is refused here
     * rather than surfacing much later as a bind failure.
     *
     * @param array<mixed> $values Columns the caller named, as they wrote them
     *
     * @return FixtureRow The same columns, as a row
     *
     * @throws InvalidOverrideException When a value is something no column could hold
     */
    public static function asRow(array $values): array
    {
        $row = [];
        foreach ($values as $column => $value) {
            $row[$column] = self::asOverride($column, $value);
        }

        return $row;
    }

    /**
     * Reads one value a caller wrote as something a column could hold.
     *
     * A column holds a scalar or null. A JSON column may also be written as an
     * array of those, which is how a caller describes its contents without
     * encoding them by hand. Anything else — an object, a resource, an array
     * of arrays — is not something a column could ever hold, so it is refused
     * here rather than surfacing much later as a bind failure.
     *
     * @param array-key $column Column the value is for
     * @param mixed $value Value as the caller wrote it
     *
     * @return FixtureOverride The value, as a column would hold it
     *
     * @throws InvalidOverrideException When the value is something no column could hold
     */
    public static function asOverride(int|string $column, mixed $value): int|float|string|bool|null|array
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }
        if (is_array($value)) {
            $members = [];
            foreach ($value as $key => $member) {
                if ($member !== null && !is_scalar($member)) {
                    throw InvalidOverrideException::nestedValue($column, get_debug_type($member));
                }
                $members[$key] = $member;
            }

            return $members;
        }

        throw InvalidOverrideException::unsupportedValue($column, get_debug_type($value));
    }
}
