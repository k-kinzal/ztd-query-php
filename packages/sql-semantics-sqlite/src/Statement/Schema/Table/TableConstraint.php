<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Statement\Node;

/**
 * One clause of the constraint list written after the columns of a table definition.
 *
 * The closed set is: a constraint name, PRIMARY KEY, UNIQUE, CHECK and FOREIGN KEY.
 * Source: https://sqlite.org/syntax/table-constraint.html.
 *
 * @visibility public
 * @example Reading a table constraint
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a, b, UNIQUE (a, b))');
 *     $create->statement->constraints[0]->items[0] instanceof \SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableConstraint // => true
 */
interface TableConstraint extends Node
{
    /**
     * Derives the facts of the operands of the clause inside the table definition.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void;
}
