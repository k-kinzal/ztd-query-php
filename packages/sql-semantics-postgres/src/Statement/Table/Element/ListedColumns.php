<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\TableConstraints;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Constraint\Constraint;

/**
 * A table defined by a column list, possibly inheriting the columns of parent tables.
 *
 * Mirrors the `tableElts` and `inhRelations` of PostgreSQL's `CreateStmt`.
 * The list may be empty: a table with no columns.
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html, https://www.postgresql.org/docs/17/ddl-inherit.html.
 *
 * @visibility SqlSemantics
 */
final class ListedColumns implements TableForm
{
    use Snapshot;

    /**
     * @var list<ColumnDefinition|LikeClause|Constraint> The column definitions, LIKE clauses and table constraints, in order
     */
    public readonly array $elements;

    /**
     * @var list<ParentTable> The parent tables
     */
    public readonly array $parents;

    /**
     * @param list<ColumnDefinition|LikeClause|Constraint> $elements The column definitions, LIKE clauses and table constraints, in order
     * @param list<ParentTable> $parents The parent tables
     */
    public function __construct(array $elements = [], array $parents = [])
    {
        $checked = [];
        foreach (Check::listOf($elements, Clause::class, 'Table elements are an ordered list of clauses.') as $element) {
            Check::input($element instanceof ColumnDefinition || $element instanceof LikeClause || ($element instanceof Constraint && (new TableConstraints())->admits($element)), 'A table element is a column definition, a LIKE clause or a table constraint.');
            $checked[] = $element;
        }
        $this->elements = $checked;
        $this->parents = Check::listOf($parents, ParentTable::class, 'Parent tables are relation names.');
    }

    /**
     * Answers the elements in order.
     *
     * @return list<Clause>
     */
    public function elements(): array
    {
        return $this->elements;
    }

    /**
     * Writes the parenthesized elements and INHERITS.
     */
    public function render(Output $out): void
    {
        $out->symbol('(')->list($this->elements)->symbol(')');
        if ($this->parents !== []) {
            $out->keyword('INHERITS')->symbol('(')->list($this->parents)->symbol(')');
        }
    }
}
