<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Statement;

/**
 * Inserts explicit rows; its source cannot be changed into a SELECT in place.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('INSERT INTO bar (foo) VALUES (1), (2)');
 *     count($statement->rows) // => 2
 *
 * @visibility public
 */
final class InsertRows
{
    /**
     * @var non-empty-list<ValuesRow>
     */
    public readonly array $rows;

    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly InsertTarget $target, ValuesRow $first, ValuesRow ...$rest)
    {
        $this->rows = [$first, ...array_values($rest)];
        assert((new \SqlSemantics\Core\Analysis\ModelGraph())->isImmutable($this), 'A statement contains only immutable semantic values.');
        foreach ($this->rows as $row) {
            assert($row->scope->dialect === $target->table->dialect, 'An INSERT uses one dialect.');
            assert(count($row->values) === count($first->values), 'All VALUES rows have the same width.');
            assert($target->width === null || count($row->values) === $target->width, 'The row width must match the insertion target.');
        }
    }

    /**
     * Returns a new INSERT with an additional row of the required width.
     */
    public function withRow(ValuesRow $row): self
    {
        return new self($this->target, $this->rows[0], ...[...array_slice($this->rows, 1), $row]);
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return 'INSERT INTO ' . $this->target->toString() . ' VALUES ' . implode(', ', array_map(static fn (ValuesRow $row): string => $row->toString(), $this->rows));
    }
}
