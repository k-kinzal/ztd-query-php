<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\TableConstraints;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\PartitionBound;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Snapshot;

/**
 * A partition: its columns are those of the partitioned parent, and its bound says which rows it holds.
 *
 * Mirrors the `partbound` form of PostgreSQL's `CreateStmt`. The parent is
 * resolved; a declared parent gives the new table its columns, types and NOT
 * NULL facts (PG-TABLE-DECLARATION-001).
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html, https://www.postgresql.org/docs/17/ddl-partitioning.html.
 *
 * @visibility public
 * @example A partition has the columns of its parent
 *     $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
 *     $parent = $semantics->analyze('CREATE TABLE p (id int NOT NULL, day date) PARTITION BY RANGE (day)', []);
 *     $part = $semantics->analyze("CREATE TABLE p1 PARTITION OF p FOR VALUES FROM ('2024-01-01') TO (MAXVALUE)", $parent->declarations());
 *     array_map(static fn ($column) => $column->name->value, $part->declarations()[0]->columns) // => ['id', 'day']
 */
final class PartitionOf implements TableForm
{
    use Snapshot;

    /**
     * @var list<Clause> The column options and table constraints; none means no parentheses are written
     */
    public readonly array $elements;

    /**
     * @param ParentTable $parent The partitioned table
     * @param PartitionBound $bound The bound of the partition
     * @param list<Clause> $elements The column options and table constraints
     */
    public function __construct(public readonly ParentTable $parent, public readonly PartitionBound $bound, array $elements = [])
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
     * Writes PARTITION OF, the parent, the parenthesized elements and the bound.
     */
    public function render(Output $out): void
    {
        $out->keyword('PARTITION', 'OF')->node($this->parent);
        if ($this->elements !== []) {
            $out->symbol('(')->list($this->elements)->symbol(')');
        }
        $out->node($this->bound);
    }
}
