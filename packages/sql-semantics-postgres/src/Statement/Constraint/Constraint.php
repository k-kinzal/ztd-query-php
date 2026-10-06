<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Constraint;

use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * A constraint of a column, a table or a domain.
 *
 * Mirrors PostgreSQL's `Constraint` node: each implementation is one value of
 * its `ConstrType`, and its expressions are derived in the environment the
 * owner of the constraint gives.
 * Source: https://www.postgresql.org/docs/17/ddl-constraints.html.
 *
 * @visibility public
 * @example Reading the kind of a column constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t (a int NOT NULL)');
 *     $create->statement->definition->elements[0]->qualifiers[0]->kind() // => \SqlSemantics\Platform\PostgreSql\Statement\Constraint\ConstraintKind::NotNull
 */
interface Constraint extends Clause
{
    /**
     * Answers the kind of the constraint.
     */
    public function kind(): ConstraintKind;
}
