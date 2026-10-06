<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * DELETE from one table, with WHERE, ORDER BY and LIMIT.
 *
 * The facts follow MYSQL-DELETE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/delete.html.
 *
 * @visibility public
 * @example Reading a single-table DELETE
 *     $delete = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('DELETE QUICK FROM t WHERE a = 1 ORDER BY b LIMIT 5');
 *     [$delete->statement->options, $delete->toString()] // => [[\SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption::Quick], 'DELETE QUICK FROM t WHERE a = 1 ORDER BY b LIMIT 5']
 */
final class Delete implements Statement
{
    use Snapshot;

    /**
     * @var list<DeleteOption> The modifiers in written order
     */
    public readonly array $options;

    /**
     * @var list<OrderItem> The ORDER BY items in written order
     */
    public readonly array $orderBy;

    /**
     * @param WithClause|null $with The common tables (MySQL 8.0 and later)
     * @param list<DeleteOption> $options The modifiers
     * @param WriteTarget $table The table rows are deleted from
     * @param Scalar|null $where The row predicate
     * @param list<OrderItem> $orderBy The order the rows are deleted in
     * @param Limit|null $limit The most rows deleted
     */
    public function __construct(
        public readonly ?WithClause $with,
        array $options,
        public readonly WriteTarget $table,
        public readonly ?Scalar $where = null,
        array $orderBy = [],
        public readonly ?Limit $limit = null,
    ) {
        $this->options = Check::listOf($options, DeleteOption::class, 'The modifiers of DELETE are delete options.');
        $this->orderBy = Check::listOf($orderBy, OrderItem::class, 'ORDER BY holds ordering items.');
    }

    /**
     * Derives the table and the clauses; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ChangeFacts())->delete($this, $derivation, $derivation->environment());
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('DELETE');
        foreach ($this->options as $option) {
            $out->keyword($option->value);
        }
        $out->keyword('FROM')->node($this->table);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        if ($this->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($this->orderBy);
        }
        $out->node($this->limit);
    }
}
