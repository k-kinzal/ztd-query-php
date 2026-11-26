<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\TableConstraints;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;

/**
 * ADD table_constraint: adds a constraint.
 *
 * Mirrors `AT_AddConstraint`. The constraint is derived where the relation is visible.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility SqlSemantics
 */
final class AddConstraint implements AlterCommand
{
    use Snapshot;

    /**
     * @param Constraint $constraint The constraint
     */
    public function __construct(public readonly Constraint $constraint)
    {
        Check::input((new TableConstraints())->admits($constraint), 'ADD takes a table constraint.');
    }

    /**
     * Derives the constraint against the relation.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->constraint->deriveClause($derivation, $environment);
    }

    /**
     * Writes ADD and the constraint.
     */
    public function render(Output $out): void
    {
        $out->keyword('ADD')->node($this->constraint);
    }
}
