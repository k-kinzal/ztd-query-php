<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Projection;

/**
 * A sort key that denotes a result position, rather than an input column.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT 1 AS one ORDER BY one');
 *     $statement->orderBy[0]->expression->position // => 1
 *
 * @visibility public
 */
final class OutputReference
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly Field $field, public readonly int $position, public readonly ?\SqlSemantics\Semantic\Name $name = null)
    {
        assert($position > 0, 'Result positions are one-based.');
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return $this->name === null ? (string) $this->position : $this->name->toString();
    }
}
