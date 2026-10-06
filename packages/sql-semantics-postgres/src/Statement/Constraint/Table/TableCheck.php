<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Attributes;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Conditions;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Writing;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A CHECK table constraint: a condition every row satisfies.
 *
 * Mirrors `CONSTR_CHECK` with `raw_expr`, `skip_validation` (NOT VALID) and
 * `is_no_inherit`. The condition is derived where the columns of the table
 * are visible (PG-TABLE-CONDITION-001); the attributes are checked by
 * PG-CONSTRAINT-ATTRIBUTES-001.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading a check that is not validated
 *     $alter = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int, CONSTRAINT positive CHECK (a > 0) NOT VALID)');
 *     [$alter->statement->definition->elements[1]->name->value, $alter->statement->definition->elements[1]->attributes] // => ['positive', [\SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintAttribute::NotValid]]
 */
final class TableCheck implements Constraint
{
    use Snapshot;

    /**
     * @var list<ConstraintAttribute> The attributes in the order written
     */
    public readonly array $attributes;

    /**
     * @param Scalar $condition The condition
     * @param list<ConstraintAttribute> $attributes The attributes in the order written
     * @param Name|null $name The constraint name
     */
    public function __construct(public readonly Scalar $condition, array $attributes = [], public readonly ?Name $name = null)
    {
        $this->attributes = (new Attributes())->checked($attributes);
    }

    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind
    {
        return ConstraintKind::Check;
    }

    /**
     * Derives the condition against the table and checks the attributes.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        (new Conditions())->derive($derivation, $this->condition, $environment, 'CHECK');
        (new Attributes())->report($derivation, $this->attributes, 'CHECK', false, true, true);
    }

    /**
     * Writes the constraint.
     */
    public function render(Output $out): void
    {
        $writing = new Writing();
        $writing->constraintName($out, $this->name);
        $out->keyword('CHECK')->symbol('(')->node($this->condition)->symbol(')');
        $writing->sequence($out, $this->attributes);
    }
}
