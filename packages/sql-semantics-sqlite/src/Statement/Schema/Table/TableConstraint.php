<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Statement\Node;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;

/**
 * One clause of the constraint list written after the columns of a table definition.
 *
 * The closed set is: a constraint name, PRIMARY KEY, UNIQUE, CHECK and FOREIGN KEY.
 * Source: https://sqlite.org/syntax/table-constraint.html.
 *
 * @visibility SqlSemantics
 */
interface TableConstraint extends Node
{
    /**
     * Derives the facts of the operands of the clause inside the table definition.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void;
}
