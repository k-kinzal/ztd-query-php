<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A CHECK table constraint.
 *
 * Rule: SQLITE-TABLE-CHECK-001. The expression may refer to any column of the
 * table and to its row identifier and is derived at a position whose only
 * visible relation is the table being defined. The grammar accepts a conflict
 * resolution after the constraint; "the conflict resolution algorithm for
 * CHECK constraints is always ABORT" and SQLite ignores the written one.
 * Source: https://sqlite.org/lang_createtable.html#check_constraints.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading a table check
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a, b, CHECK (a < b))');
 *     $create->facts->scalar($create->statement->constraints[0]->items[0]->expression->right)->resolution->slot->name->value // => 'b'
 */
final class TableCheck implements TableConstraint
{
    use Snapshot;

    /**
     * @param Scalar $expression The condition every row must not fail
     * @param ConflictResolution|null $conflict The written ON CONFLICT resolution, which SQLite ignores
     */
    public function __construct(public readonly Scalar $expression, public readonly ?ConflictResolution $conflict = null)
    {
    }

    /**
     * Derives the condition where the columns and the row identifier of the table are visible.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
        $derivation->scalar($this->expression, $scope->row);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('CHECK')->symbol('(')->node($this->expression)->symbol(')');
        if ($this->conflict !== null) {
            $out->keyword('ON', 'CONFLICT', $this->conflict->value);
        }
    }
}
