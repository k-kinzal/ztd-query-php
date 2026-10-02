<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema\Definition;

use SqlSemantics\Statement\Identifier\Name;

/**
 * Uniqueness of the containing column, with its violation policy.
 * @visibility public
 * @example Reconstructing a column rule
 *     (new \SqlSemantics\Statement\Schema\Definition\ColumnUnique())->toString() // => 'UNIQUE'
 */
final class ColumnUnique implements ColumnConstraint
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Describes the constraint without evaluating it against stored rows.
     */
    public function __construct(public readonly ConflictAction $conflict = ConflictAction::Implicit, public readonly ?Name $name = null)
    {
    }

    /**
     * Retains the constraint name and requested conflict handling.
     */
    public function toString(): string
    {
        return ($this->name === null ? '' : 'CONSTRAINT ' . $this->name->toString() . ' ') . 'UNIQUE' . ($this->conflict === ConflictAction::Implicit ? '' : ' ON CONFLICT ' . $this->conflict->value);
    }
}
