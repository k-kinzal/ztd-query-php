<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Scalar;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;

/**
 * A DEFAULT column constraint whose value is an expression in parentheses.
 *
 * Rule: SQLITE-COLUMN-DEFAULT-EXPRESSION-001. The expression is evaluated
 * once for each inserted row that gives the column no value. It must be
 * constant: "an expression is considered constant if it contains no
 * sub-queries, column or table references, bound parameters, or string
 * literals enclosed in double-quotes". It is therefore derived at a position
 * that sees no relation, where a column name is a missing column.
 * Source: https://sqlite.org/lang_createtable.html#the_default_clause.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class DefaultExpression implements ColumnConstraint
{
    use Snapshot;

    /**
     * @param Scalar $expression The expression written in parentheses
     */
    public function __construct(public readonly Scalar $expression)
    {
    }

    /**
     * Derives the expression where no column is visible.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
        $derivation->scalar($this->expression, $scope->constant);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('DEFAULT')->symbol('(')->node($this->expression)->symbol(')');
    }
}
