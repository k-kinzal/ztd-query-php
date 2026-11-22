<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Column;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;

/**
 * A NULL column constraint: the column may hold NULL, which is the default. Mirrors `CONSTR_NULL`; it conflicts with NOT NULL on the same column.
 *
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility SqlSemantics
 */
final class NullAllowed implements Constraint
{
    use Snapshot;

    /**
     * @param Name|null $name The constraint name
     */
    public function __construct(public readonly ?Name $name = null)
    {
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::Null;
    }

    /**
     * Derives nothing: the constraint has no operand.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        (new Writing())->constraintName($out, $this->name);
        $out->keyword('NULL');
    }
}
