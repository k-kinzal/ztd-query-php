<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\TableConstraints;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A typed table: its columns are the attributes of a composite type.
 *
 * Mirrors the `ofTypename` of PostgreSQL's `CreateStmt` with the column
 * options and table constraints written after it. A version 1 context
 * declares no types, so the new table's columns depend on the type's
 * definition (PG-TABLE-DECLARATION-001).
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading a typed table
 *     $create = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TABLE t OF person (PRIMARY KEY (id))');
 *     [$create->statement->definition->type->last()->value, $create->declarations()[0]->complete] // => ['person', false]
 */
final class TypedTable implements TableForm
{
    use Snapshot;

    /**
     * @var list<Clause> The column options and table constraints; none means no parentheses are written
     */
    public readonly array $elements;

    /**
     * @param DottedName $type The composite type
     * @param list<Clause> $elements The column options and table constraints
     */
    public function __construct(public readonly DottedName $type, array $elements = [])
    {
        $this->elements = (new TableConstraints())->typedElements($elements);
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
     * Writes OF, the type and the parenthesized elements.
     */
    public function render(Output $out): void
    {
        $out->keyword('OF')->node($this->type);
        if ($this->elements !== []) {
            $out->symbol('(')->list($this->elements)->symbol(')');
        }
    }
}
