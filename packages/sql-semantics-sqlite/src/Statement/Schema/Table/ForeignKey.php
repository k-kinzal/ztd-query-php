<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Table;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\ListedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DecoratedColumnName;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\UnknownColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\Deferrability;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Reference\ForeignKeyClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A FOREIGN KEY table constraint.
 *
 * Rule: SQLITE-TABLE-FOREIGN-KEY-001. The child columns are columns of the
 * table being defined; a name that is none is reported ("unknown column in
 * foreign key definition"), and so is a child column written with a collation
 * or a sort order. The parent is not looked up when the table is defined.
 * Source: https://sqlite.org/foreignkeys.html. Status: Implemented.
 *
 * @visibility public
 * @example Reading a composite foreign key
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE c (a, b, FOREIGN KEY (a, b) REFERENCES parent (x, y) DEFERRABLE INITIALLY DEFERRED)');
 *     $key = $create->statement->constraints[0]->items[0];
 *     [count($key->columns), $key->clause->table->value, $key->deferrability?->deferred()] // => [2, 'parent', true]
 */
final class ForeignKey implements TableConstraint
{
    use Snapshot;

    /**
     * @var non-empty-list<ListedColumn> The child columns in written order
     */
    public readonly array $columns;

    /**
     * @param list<ListedColumn> $columns The child columns in written order; at least one
     * @param ForeignKeyClause $clause The parent, its key columns and the actions
     * @param Deferrability|null $deferrability The written DEFERRABLE clause
     */
    public function __construct(array $columns, public readonly ForeignKeyClause $clause, public readonly ?Deferrability $deferrability = null)
    {
        $this->columns = Check::listOf($columns, ListedColumn::class, 'A foreign key has at least one child column.', 1);
    }

    /**
     * Reports child columns that the table does not have or that are decorated, then derives the parent clause.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
        foreach ($this->columns as $column) {
            if ($column->collation !== null || $column->direction !== null) {
                $derivation->report(new DecoratedColumnName($column->name));
            }
            if ($scope->lacks($column->name)) {
                $derivation->report(new UnknownColumn($column->name));
            }
        }
        $this->clause->deriveConstraint($derivation, $scope);
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('FOREIGN', 'KEY')->symbol('(')->list($this->columns)->symbol(')')->node($this->clause)->node($this->deferrability);
    }
}
