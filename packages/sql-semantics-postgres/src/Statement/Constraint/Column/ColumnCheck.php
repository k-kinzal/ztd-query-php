<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Conditions;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;

/**
 * A CHECK column constraint: a condition every row satisfies.
 *
 * Mirrors `CONSTR_CHECK` with `raw_expr` and `is_no_inherit`. The condition
 * is derived where the columns of the table are visible
 * (PG-TABLE-CONDITION-001); a column constraint may refer to any column.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html, https://www.postgresql.org/docs/17/ddl-constraints.html#DDL-CONSTRAINTS-CHECK-CONSTRAINTS.
 *
 * @visibility SqlSemantics
 */
final class ColumnCheck implements Constraint
{
    use Snapshot;

    /**
     * @param Scalar $condition The condition
     * @param bool $noInherit Whether NO INHERIT is written: the constraint does not apply to child tables
     * @param Name|null $name The constraint name
     */
    public function __construct(public readonly Scalar $condition, public readonly bool $noInherit = false, public readonly ?Name $name = null)
    {
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::Check;
    }

    /**
     * Derives the condition against the table.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Conditions())->derive($derivation, $this->condition, $environment, 'CHECK');
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        (new Writing())->constraintName($out, $this->name);
        $out->keyword('CHECK')->symbol('(')->node($this->condition)->symbol(')');
        if ($this->noInherit) {
            $out->keyword('NO', 'INHERIT');
        }
    }
}
