<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Alter;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\TableConstraints;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * ADD table_constraint: adds a constraint.
 *
 * Mirrors `AT_AddConstraint`. The constraint is derived where the relation is visible.
 * Source: https://www.postgresql.org/docs/17/sql-altertable.html.
 *
 * @visibility public
 * @example Adding a constraint
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TABLE t ADD CONSTRAINT c CHECK (a > 0) NOT VALID');
 *     $statement->toString() // => 'ALTER TABLE t ADD CONSTRAINT c CHECK (a > 0) NOT VALID'
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
