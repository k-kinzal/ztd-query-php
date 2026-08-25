<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use InvalidArgumentException;

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
 */
final class RowSpec
{
    /**
     * @param int|null $count How many rows the caller asked for, or null when the count is free
     * @param list<array<string, mixed>>|null $rows Overrides per row, or null when the caller described the rows together
     * @param array<string, mixed> $sharedOverrides Overrides every row of the table carries
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
     * @throws InvalidArgumentException When a row count is negative
     */
    public static function from(string $table, int|array|TableOverrides $spec): self
    {
        if (is_int($spec)) {
            if ($spec < 0) {
                throw new InvalidArgumentException(
                    sprintf('The row count for %s cannot be negative, got %d.', $table, $spec)
                );
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

        /** @var array<string, mixed> $spec */
        return new self(null, null, $spec);
    }

    /**
     * Answers the overrides one row carries.
     *
     * @param int $index Which row of the table
     *
     * @return array<string, mixed> Column name => the value that row must carry
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
     * @return list<array<string, mixed>>|null One entry per row, or null when the rows were described together
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

            /** @var array<string, mixed> $entry */
            $rows[] = $entry;
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
     * @throws InvalidArgumentException When a row count is negative
     */
    public static function forTables(array $overrides): array
    {
        $specs = [];
        foreach ($overrides as $table => $spec) {
            $specs[$table] = self::from($table, $spec);
        }

        return $specs;
    }
}
