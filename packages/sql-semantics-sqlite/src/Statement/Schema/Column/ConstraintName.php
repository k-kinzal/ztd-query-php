<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableConstraint;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A `CONSTRAINT name` clause.
 *
 * Rule: SQLITE-CONSTRAINT-NAME-001. In the grammar the clause is an element
 * of the constraint list of its own, not a part of the constraint after it:
 * it may be repeated and it may be the last element. SQLite remembers the most
 * recent name and uses it only to name the CHECK constraints that follow in
 * the error they raise. The remembered name is forgotten at the start of the
 * next column definition and at a comma between table constraints, so the
 * model keeps the clause at its written place.
 * Source: https://sqlite.org/syntax/column-constraint.html,
 * https://sqlite.org/syntax/table-constraint.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a constraint name that precedes a CHECK
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE t (a CONSTRAINT positive CHECK (a > 0))');
 *     $create->statement->columns[0]->constraints[0]->name->value // => 'positive'
 */
final class ConstraintName implements ColumnConstraint, TableConstraint
{
    use Snapshot;

    /**
     * @param Name $name The constraint name
     */
    public function __construct(public readonly Name $name)
    {
    }

    /**
     * Derives nothing: the clause has no operand that depends on a declaration.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('CONSTRAINT')->name($this->name, NameUse::Label);
    }
}
