<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Model\Statement\Spelling\Layout;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Expression\Limits;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefinitionPosition;

/**
 * A CHECK column constraint.
 *
 * Rule: SQLITE-COLUMN-CHECK-001. The expression is evaluated for each written
 * row and may refer to any column of the table and to its row identifier, not
 * only to the column it is written after. It is derived at a position whose
 * only visible relation is the table being defined. A bound parameter or a
 * subquery in it is a diagnostic (SQLITE-DEFINITION-LIMITS-001).
 * Source: https://sqlite.org/lang_createtable.html#check_constraints.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class ColumnCheck implements ColumnConstraint
{
    use Snapshot;

    /**
     * @param Scalar $expression The condition every row must not fail
     */
    public function __construct(public readonly Scalar $expression, public readonly ?Layout $labelLayout = null)
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
    }
}
