<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Insertion;

use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Query\Select;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;

/**
 * Inserts rows from a concrete select source while retaining target declaration identities.
 * Changing the source form constructs a different operation type.
 * @visibility public
 * @example Identifying the distinct insertion form
 *     is_subclass_of(\SqlSemantics\Statement\Insertion\InsertSelect::class, \SqlSemantics\Statement\Operation::class) // => true
 */
final class InsertSelect implements Operation
{
    /**
     * Target and source have distinct scopes but share one explicit declaration context.
     */
    public function __construct(public readonly Target $target, public readonly Select $query, public readonly ConflictAction $conflict = ConflictAction::Implicit, public readonly bool $replaceKeyword = false)
    {
        assert($target->table->catalog === $query->scope->catalog, 'Insertion source and target must share the declaration context.');
        assert(!$replaceKeyword || $conflict === ConflictAction::Replace, 'REPLACE requests replacement conflict handling.');
    }

    /**
     * Reports an input-width contradiction without confusing it with a missing declaration.
     */
    public function arity(): Arity
    {
        return $this->target->arity(count($this->query->fields()->items));
    }

    /**
     * Reconstructs the insertion from target bindings, source expressions, and conflict behavior.
     */
    public function toString(): string
    {
        $command = $this->replaceKeyword ? 'REPLACE' : 'INSERT' . ($this->conflict === ConflictAction::Implicit ? '' : ' OR ' . $this->conflict->value);
        return $command . ' INTO ' . $this->target->toString() . ' ' . $this->query->toString();
    }
}
