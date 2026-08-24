<?php

declare(strict_types=1);

namespace SqlFixture\Fixture;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use LogicException;
use OutOfBoundsException;
use Traversable;

/**
 * What a plan generated, one entry per table.
 *
 * Entries can be read by position, which is the order the plan names its
 * tables, or by table name:
 *
 *     [$order, $details] = $faker->fixtures(...);
 *     $details = $fixtures['order_detail'];
 *
 * Rows are held as lists throughout and only presented as one row or many at
 * the edge. Holding either shape in the same field would leave every reader,
 * and every analyser, guessing which one it had.
 *
 * @implements ArrayAccess<int|string, array<string, mixed>|list<array<string, mixed>>|null>
 * @implements IteratorAggregate<int, array<string, mixed>|list<array<string, mixed>>|null>
 */
final class FixtureSet implements ArrayAccess, IteratorAggregate, Countable
{
    /**
     * @param array<string, list<array<string, mixed>>> $rows
     * @param array<string, bool> $lists Table => reads back as a list
     * @param list<string> $order Table names in the order the plan names them
     */
    public function __construct(
        private readonly array $rows,
        private readonly array $lists,
        private readonly array $order,
    ) {
    }

    /**
     * @return array<string, mixed>|list<array<string, mixed>>|null
     */
    public function get(int|string $table): ?array
    {
        $name = $this->resolve($table);

        return ($this->lists[$name] ?? false) ? $this->rows($name) : $this->firstRow($name);
    }

    /**
     * The single row generated for a table.
     *
     * @return array<string, mixed>|null
     * @throws OutOfBoundsException If the table holds a list rather than one row
     */
    public function row(int|string $table): ?array
    {
        $name = $this->resolve($table);

        if ($this->lists[$name] ?? false) {
            throw new OutOfBoundsException(sprintf(
                '%s holds a list of rows, so read it with rows() instead.',
                $name
            ));
        }

        return $this->firstRow($name);
    }

    /**
     * The rows generated for a table, as a list even where there is only one.
     *
     * @return list<array<string, mixed>>
     */
    public function rows(int|string $table): array
    {
        return $this->rows[$this->resolve($table)] ?? [];
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        return $this->order;
    }

    /**
     * @return array<string, array<string, mixed>|list<array<string, mixed>>|null>
     */
    public function toArray(): array
    {
        $entries = [];
        foreach ($this->order as $table) {
            $entries[$table] = $this->get($table);
        }

        return $entries;
    }

    public function offsetExists(mixed $offset): bool
    {
        return in_array($this->resolve($offset), $this->order, true);
    }

    public function offsetGet(mixed $offset): ?array
    {
        return $this->get($offset);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('A FixtureSet is read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('A FixtureSet is read-only.');
    }

    public function getIterator(): Traversable
    {
        foreach ($this->order as $table) {
            yield $this->get($table);
        }
    }

    public function count(): int
    {
        return count($this->order);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function firstRow(string $table): ?array
    {
        return ($this->rows[$table] ?? [])[0] ?? null;
    }

    /**
     * Positions read in the order the plan names its tables, which is what
     * makes [$order, $details] = ... line up with the plan as written.
     */
    private function resolve(int|string $table): string
    {
        return is_int($table) ? ($this->order[$table] ?? '') : $table;
    }
}
