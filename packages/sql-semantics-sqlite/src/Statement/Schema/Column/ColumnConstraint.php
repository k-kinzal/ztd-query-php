<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Statement\Node;

/**
 * One clause written after the name and type of a column, in written order.
 *
 * The closed set is: a constraint name, a default, NULL, NOT NULL, PRIMARY
 * KEY, UNIQUE, CHECK, a REFERENCES clause, a DEFERRABLE clause, a collation
 * and a generated column expression.
 * Source: https://sqlite.org/syntax/column-constraint.html.
 *
 * @visibility public
 * @example Reading the constraints of a column in written order
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a INTEGER NOT NULL DEFAULT 0)');
 *     count($create->statement->columns[0]->constraints) // => 2
 */
interface ColumnConstraint extends Node
{
    /**
     * Derives the facts of the operands of the clause inside the table definition.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void;
}
