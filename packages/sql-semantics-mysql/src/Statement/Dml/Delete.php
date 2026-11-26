<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Platform\MySql\Model\Construction\Derivation;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Rendering\Output;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;
use SqlSemantics\Platform\MySql\Model\Statement\Statement;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;

/**
 * DELETE from one table, with WHERE, ORDER BY and LIMIT.
 *
 * The facts follow MYSQL-DELETE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/delete.html.
 *
 * @visibility SqlSemantics
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
