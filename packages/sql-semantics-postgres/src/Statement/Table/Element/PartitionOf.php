<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Table\Element;

use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Rules\Table\Definition\TableConstraints;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Table\Partition\PartitionBound;

/**
 * A partition: its columns are those of the partitioned parent, and its bound says which rows it holds.
 *
 * Mirrors the `partbound` form of PostgreSQL's `CreateStmt`. The parent is
 * resolved; a declared parent gives the new table its columns, types and NOT
 * NULL facts (PG-TABLE-DECLARATION-001).
 * Source: https://www.postgresql.org/docs/17/sql-createtable.html, https://www.postgresql.org/docs/17/ddl-partitioning.html.
 *
 * @visibility SqlSemantics
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
