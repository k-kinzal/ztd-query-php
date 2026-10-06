<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Schema\Reference;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Definition\ConstraintScope;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\ListedColumn;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Column\ColumnConstraint;
use SqlSemantics\Platform\Sqlite\Statement\Schema\Problem\DecoratedColumnName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A REFERENCES clause: the parent table of a foreign key, its key columns and the actions.
 *
 * Rule: SQLITE-FK-CLAUSE-001. Written as a column constraint, the clause makes
 * the column a foreign key of one column. The parent is named without a
 * schema and belongs to the schema of the child table. SQLite does not look
 * the parent up when the table is defined ("the parent table or parent key
 * columns do not exist" is found when rows are changed), so the model records
 * no resolution and no diagnostic for the parent. A parent column written
 * with a collation or a sort order is a syntax error in SQLite and is
 * reported as a diagnostic.
 * Source: https://sqlite.org/foreignkeys.html, https://sqlite.org/syntax/foreign-key-clause.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the parent of a foreign key
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('CREATE TABLE c (p REFERENCES parent (id) ON DELETE CASCADE)');
 *     $clause = $create->statement->columns[0]->constraints[0];
 *     [$clause->table->value, $clause->columns[0]->name->value, count($clause->arguments)] // => ['parent', 'id', 1]
 */
final class ForeignKeyClause implements ColumnConstraint
{
    use Snapshot;

    /**
     * @var list<ListedColumn>|null The parent key columns, or null when none are written and the primary key of the parent is meant
     */
    public readonly ?array $columns;

    /**
     * @var list<ReferenceArgument> The actions and MATCH names in written order
     */
    public readonly array $arguments;

    /**
     * @param Name $table The parent table
     * @param list<ListedColumn>|null $columns The parent key columns; null when none are written, otherwise at least one
     * @param list<ReferenceArgument> $arguments The actions and MATCH names in written order
     */
    public function __construct(public readonly Name $table, ?array $columns = null, array $arguments = [])
    {
        $this->columns = $columns === null ? null : Check::listOf($columns, ListedColumn::class, 'A written parent column list names at least one column.', 1);
        $this->arguments = Check::listOf($arguments, ReferenceArgument::class, 'The clauses after a foreign key parent are actions and MATCH names.');
    }

    /**
     * Answers the reaction in effect for an event: that of the last action written for it, or no action.
     */
    public function reaction(ReferenceEvent $event): ReferenceReaction
    {
        $reaction = ReferenceReaction::NoAction;
        foreach ($this->arguments as $argument) {
            if ($argument instanceof ReferenceAction && $argument->event === $event) {
                $reaction = $argument->reaction;
            }
        }

        return $reaction;
    }

    /**
     * Reports parent columns written with a collation or a sort order.
     */
    public function deriveConstraint(Derivation $derivation, ConstraintScope $scope): void
    {
        foreach ($this->columns ?? [] as $column) {
            if ($column->collation !== null || $column->direction !== null) {
                $derivation->report(new DecoratedColumnName($column->name));
            }
        }
    }

    /**
     * Writes the clause.
     */
    public function render(Output $out): void
    {
        $out->keyword('REFERENCES')->name($this->table, NameUse::Relation);
        if ($this->columns !== null) {
            $out->symbol('(')->list($this->columns)->symbol(')');
        }
        foreach ($this->arguments as $argument) {
            $out->node($argument);
        }
    }
}
