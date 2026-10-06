<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Query\Positions;
use SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Snapshot;

/**
 * The ORDER BY, LIMIT, OFFSET, FETCH and locking clauses written after a query body.
 *
 * Mirrors the `sortClause`, `limitOffset`, `limitCount`, `limitOption` and
 * `lockingClause` that PostgreSQL attaches to a `SelectStmt`. The locking
 * clauses may be written before or after the limit; the order is kept. FOR
 * READ ONLY requests no lock. An integer constant in ORDER BY is an output
 * position and must be given as one.
 * Source: https://www.postgresql.org/docs/17/sql-select.html.
 *
 * @visibility public
 * @example Reading the options of a selection
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('SELECT 1 ORDER BY 1 FOR UPDATE LIMIT 1');
 *     [count($query->statement->options->order), $query->statement->options->lockingFirst, $query->toString()] // => [1, true, 'SELECT 1 ORDER BY 1 FOR UPDATE LIMIT 1']
 * @example Refusing an integer constant that is not given as a position
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\SelectOptions([new \SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('1')))]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class SelectOptions implements Node
{
    use Snapshot;

    /**
     * @var list<SortItem> The ORDER BY items in written order
     */
    public readonly array $order;

    /**
     * @var list<LockingClause> The locking clauses in written order
     */
    public readonly array $locking;

    /**
     * @param list<SortItem> $order The ORDER BY items in written order
     * @param RowLimit|null $limit The LIMIT, FETCH and OFFSET clauses
     * @param list<LockingClause> $locking The locking clauses in written order
     * @param bool $readOnly Whether FOR READ ONLY is written instead of locking clauses
     * @param bool $lockingFirst Whether the locking clauses are written before the limit
     */
    public function __construct(array $order = [], public readonly ?RowLimit $limit = null, array $locking = [], public readonly bool $readOnly = false, public readonly bool $lockingFirst = false)
    {
        $this->order = Check::listOf($order, SortItem::class, 'ORDER BY holds sort items.');
        $this->locking = Check::listOf($locking, LockingClause::class, 'The locking clauses are locking clauses.');
        Check::input($this->order !== [] || $limit !== null || $this->locking !== [] || $readOnly, 'Query options hold at least one clause.');
        Check::input(!$readOnly || $this->locking === [], 'FOR READ ONLY is written instead of locking clauses.');
        Check::input(!$lockingFirst || ($limit !== null && ($readOnly || $this->locking !== [])), 'Only locking clauses written with a limit can come first.');
        foreach ($this->order as $item) {
            Check::input((new Positions())->value($item->expression) === null, 'An integer constant in ORDER BY is an output position.');
        }
    }

    /**
     * Tells whether locking clauses or FOR READ ONLY are written.
     */
    public function locks(): bool
    {
        return $this->readOnly || $this->locking !== [];
    }

    /**
     * Writes ORDER BY, then the limit and the locking clauses in the order written.
     */
    public function render(Output $out): void
    {
        if ($this->order !== []) {
            $out->keyword('ORDER', 'BY')->list($this->order);
        }
        if ($this->lockingFirst) {
            $this->writeLocking($out);
            $out->node($this->limit);

            return;
        }
        $out->node($this->limit);
        $this->writeLocking($out);
    }

    /**
     * Writes the locking clauses or FOR READ ONLY.
     */
    public function writeLocking(Output $out): void
    {
        if ($this->readOnly) {
            $out->keyword('FOR', 'READ', 'ONLY');
        }
        foreach ($this->locking as $clause) {
            $out->node($clause);
        }
    }
}
