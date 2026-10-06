<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Rules\Definition\Expression\Limits;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Limit\DefinitionPosition;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

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
 * @visibility public
 * @example Resolving a column inside a CHECK constraint to the column being defined
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a INT CHECK (a > 0))');
 *     $check = $create->statement->columns[0]->constraints[0];
 *     $create->facts->scalar($check->expression->left)->resolution->slot->column === $create->declarations()[0]->columns[0] // => true
 */
final class ColumnCheck implements ColumnConstraint
{
    use Snapshot;

    /**
     * @param Scalar $expression The condition every row must not fail
     */
    public function __construct(public readonly Scalar $expression)
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
        $out->keyword('CHECK')->symbol('(')->node($this->expression)->symbol(')');
    }
}
