<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Column;

use SqlSemantics\Platform\Sqlite\Model\Construction\Derivation;
use SqlSemantics\Platform\Sqlite\Model\Contract\NameUse;
use SqlSemantics\Platform\Sqlite\Model\Rendering\Output;
use SqlSemantics\Platform\Sqlite\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Table\TableConstraint;

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
 * @visibility SqlSemantics
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
