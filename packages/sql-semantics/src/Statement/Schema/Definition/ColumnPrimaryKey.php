<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Schema\Definition;

use SqlSemantics\Statement\Identifier\Name;

/**
 * A single-column primary key with ordering, allocation, and violation behavior.
 * @visibility public
 * @example Reconstructing a column rule
 *     (new \SqlSemantics\Statement\Schema\Definition\ColumnPrimaryKey())->toString() // => 'PRIMARY KEY'
 */
final class ColumnPrimaryKey implements ColumnConstraint
{
    use \SqlSemantics\Statement\Validation\Snapshot;

    /**
     * Describes the constraint without evaluating it against stored rows.
     */
    public function __construct(public readonly KeyDirection $direction = KeyDirection::Implicit, public readonly ConflictAction $conflict = ConflictAction::Implicit, public readonly bool $autoIncrement = false, public readonly ?Name $name = null)
    {
    }

    /**
     * Retains the constraint name and requested conflict handling.
     */
    public function toString(): string
    {
        return ($this->name === null ? '' : 'CONSTRAINT ' . $this->name->toString() . ' ') . 'PRIMARY KEY' . ($this->direction === KeyDirection::Implicit ? '' : ' ' . $this->direction->value) . ($this->conflict === ConflictAction::Implicit ? '' : ' ON CONFLICT ' . $this->conflict->value) . ($this->autoIncrement ? ' AUTOINCREMENT' : '');
    }
}
