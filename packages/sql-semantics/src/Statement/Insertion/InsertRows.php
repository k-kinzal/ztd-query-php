<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Insertion;

use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Query\Rows;
use SqlSemantics\Statement\Schema\Definition\ConflictAction;

/**
 * Inserts rows from a concrete rows source while retaining target declaration identities.
 * Changing the source form constructs a different operation type.
 * @visibility public
 * @example Identifying the distinct insertion form
 *     is_subclass_of(\SqlSemantics\Statement\Insertion\InsertRows::class, \SqlSemantics\Statement\Operation::class) // => true
 */
final class InsertRows implements Operation
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Target and source have distinct scopes but share one explicit declaration context.
     */
    public function __construct(public readonly Target $target, public readonly Rows $rows, public readonly ConflictAction $conflict = ConflictAction::Implicit, public readonly bool $replaceKeyword = false)
    {
        \SqlSemantics\Statement\Validation\Check::input($target->table->catalog === $rows->scope->catalog, 'Insertion source and target must share the declaration context.');
        \SqlSemantics\Statement\Validation\Check::input($rows->scope->parent === null && $rows->scope->tables === [], 'Insertion rows have an independent expression scope without target or outer inputs.');
        \SqlSemantics\Statement\Validation\Check::input(!$replaceKeyword || $conflict === ConflictAction::Replace, 'REPLACE requests replacement conflict handling.');
    }

    /**
     * Reports an input-width contradiction without confusing it with a missing declaration.
     */
    public function arity(): Arity
    {
        return $this->target->arity(...$this->rows->widths());
    }

    /**
     * Reconstructs the insertion from target bindings, source expressions, and conflict behavior.
     */
    public function toString(): string
    {
        $command = $this->replaceKeyword ? 'REPLACE' : 'INSERT' . ($this->conflict === ConflictAction::Implicit ? '' : ' OR ' . $this->conflict->value);
        return $command . ' INTO ' . $this->target->toString() . ' ' . $this->rows->toString();
    }
}
