<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Expression\Precedence;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;

/**
 * A DEFAULT column constraint: the value a column takes when none is given.
 *
 * Mirrors `CONSTR_DEFAULT` with `raw_expr`. The grammar reads a `b_expr`, so
 * the expression has no boolean, IS, pattern or subquery operator outside
 * parentheses. "The value is any variable-free expression": the expression
 * sees no column, so it is derived where no relation is visible.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
 */
final class DefaultExpression implements Constraint
{
    use Snapshot;

    /**
     * @param Scalar $value The default expression; a `b_expr`
     * @param Name|null $name The constraint name
     */
    public function __construct(public readonly Scalar $value, public readonly ?Name $name = null)
    {
        Check::input((new Precedence())->restricted($value), 'A default is an expression without boolean, IS, pattern or subquery operators outside parentheses.');
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::Default;
    }

    /**
     * Derives the expression where no column is visible.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->value, $derivation->environment());
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        (new Writing())->constraintName($out, $this->name);
        $out->keyword('DEFAULT')->node($this->value);
    }
}
