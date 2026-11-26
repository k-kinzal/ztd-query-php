<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;

/**
 * One clause written after the name and type of a column, in written order.
 *
 * The closed set is: a constraint name, a default, NULL, NOT NULL, PRIMARY
 * KEY, UNIQUE, CHECK, a REFERENCES clause, a DEFERRABLE clause, a collation
 * and a generated column expression.
 * Source: https://sqlite.org/syntax/column-constraint.html.
 *
 * @visibility SqlSemantics
 */
interface ColumnConstraint extends Node
{
    /**
     * Derives the facts of the operands of the clause inside the table definition.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void;
}
