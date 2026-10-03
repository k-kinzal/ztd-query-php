<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Insertion;

use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;

/**
 * Requests one row obtained from the table defaults, without evaluating those defaults.
 * @visibility public
 * @example Identifying insertion from defaults as its own operation
 *     is_subclass_of(\SqlSemantics\Statement\Insertion\InsertDefaults::class, \SqlSemantics\Statement\Operation::class) // => true
 */
final class InsertDefaults implements Operation
{
    /**
     * Defaults are requested from the target; they are not read as another query or value row.
     */
    public function __construct(public readonly Target $target, public readonly ConflictAction $conflict = ConflictAction::Implicit, public readonly bool $replaceKeyword = false)
    {
        assert(!$replaceKeyword || $conflict === ConflictAction::Replace, 'REPLACE requests replacement conflict handling.');
    }

    /**
     * DEFAULT VALUES supplies no explicit input expressions for a named destination list.
     */
    public function arity(): Arity
    {
        return $this->target->explicitColumns ? Arity::Mismatch : Arity::Matching;
    }

    /**
     * Reconstructs the default-row request and its conflict behavior.
     */
    public function toString(): string
    {
        $command = $this->replaceKeyword ? 'REPLACE' : 'INSERT' . ($this->conflict === ConflictAction::Implicit ? '' : ' OR ' . $this->conflict->value);
        return $command . ' INTO ' . $this->target->toString() . ' DEFAULT VALUES';
    }
}
