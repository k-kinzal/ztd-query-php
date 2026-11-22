<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Layout;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Expression\Limits;
use SqlSemantics\Platform\Sqlite\Statement\Mutation\ConflictResolution;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefinitionPosition;

/**
 * A CHECK table constraint.
 *
 * Rule: SQLITE-TABLE-CHECK-001. The expression may refer to any column of the
 * table and to its row identifier and is derived at a position whose only
 * visible relation is the table being defined. The grammar accepts a conflict
 * resolution after the constraint; "the conflict resolution algorithm for
 * CHECK constraints is always ABORT" and SQLite ignores the written one. A
 * bound parameter or a subquery in the condition is a diagnostic
 * (SQLITE-DEFINITION-LIMITS-001).
 * Source: https://sqlite.org/lang_createtable.html#check_constraints.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class TableCheck implements TableConstraint
{
    use Snapshot;

    /**
     * @param Scalar $expression The condition every row must not fail
     * @param ConflictResolution|null $conflict The written ON CONFLICT resolution, which SQLite ignores
     */
    public function __construct(public readonly Scalar $expression, public readonly ?ConflictResolution $conflict = null, public readonly ?Layout $labelLayout = null)
    {
    }

    /**
     * Derives the condition where the columns and the row identifier of the table are visible.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
        $derivation->scalar($this->expression, $scope->row);
        (new Limits())->report($this->expression, DefinitionPosition::CheckConstraint, $derivation);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('CHECK')->symbol('(')->layout($this->labelLayout, $this->expression)->symbol(')');
        if ($this->conflict !== null) {
            $out->keyword('ON', 'CONFLICT', $this->conflict->value);
        }
    }
}
