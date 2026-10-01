<?php

declare(strict_types=1);

namespace SqlSemantics\Semantic\Statement;

use SqlSemantics\Semantic\Projection\Star;

/**
 * Inserts a query result, with a separately scoped source.
 * @example Reading semantic relationships
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('INSERT INTO bar (foo) SELECT foo FROM other');
 *     $statement->source->tables[0]->name->name->value // => 'other'
 *
 * @visibility public
 */
final class InsertSelect
{
    /**
     * Constructs the value and asserts the relationships required by its fields.
     */
    public function __construct(public readonly InsertTarget $target, public readonly Select $source)
    {
        assert($source->scope->dialect === $target->table->dialect, 'An INSERT uses one dialect.');
        assert((new \SqlSemantics\Core\Analysis\ModelGraph())->isImmutable($this), 'A statement contains only immutable semantic values.');
        foreach ($source->fields()->items as $item) {
            if ($item instanceof Star && $item->fields === null) {
                return;
            }
        }
        assert($target->width === null || $target->width === count($source->fields()->outputs()), 'The source width must match the insertion target.');
    }

    /**
     * Returns a new INSERT SELECT with a source of the required result width.
     */
    public function withSource(Select $source): self
    {
        return new self($this->target, $source);
    }

    /**
     * Reconstructs SQL from the semantic values without consulting source syntax.
     */
    public function toString(): string
    {
        return 'INSERT INTO ' . $this->target->toString() . ' ' . $this->source->toString();
    }
}
