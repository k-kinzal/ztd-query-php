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
 * @template TValue = mixed
 */
final class RowSpec
{
    /**
     * @param list<array<TValue>>|null $rows Overrides per row, or null when the count is free
     * @param array<TValue> $sharedOverrides
     */
    public function __construct(
        public readonly ?int $count,
        private readonly ?array $rows,
        private readonly array $sharedOverrides,
    ) {
    }

    /**
     * Leaves both the row count and column values to generation.
     */
    public static function unspecified(): self
    {
        return new self(null, null, []);
    }

    /**
     * @param int|array<mixed>|TableOverrides $spec
     * @throws InvalidArgumentException If the shape is not one of the four
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

        $rows = (new OverrideRows())->asRows($spec);
        if ($rows !== null) {
            return new self(count($rows), $rows, []);
        }

        return new self(null, null, $spec);
    }

    /**
     * Overrides for one row of the given index.
     *
     * @return array<mixed>
     */
    public function overridesFor(int $index): array
    {
        if ($this->rows === null) {
            return $this->sharedOverrides;
        }

        return $this->rows[$index] ?? [];
    }

}
