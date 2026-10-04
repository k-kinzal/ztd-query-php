<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Dml;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Rules\Dml\ChangeFacts;
use SqlSemantics\Platform\MySql\Statement\Query\Limit;
use SqlSemantics\Platform\MySql\Statement\Query\OrderItem;
use SqlSemantics\Platform\MySql\Statement\Query\WithClause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Statement\Relation;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Statement;

/**
 * UPDATE of one table or of several tables, with SET assignments, WHERE, ORDER BY and LIMIT.
 *
 * Mirrors the server's `PT_update`: the statement is a multiple-table
 * UPDATE when its table references hold more than one table. The facts
 * follow MYSQL-UPDATE-001.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/update.html.
 *
 * @visibility public
 * @example Reading an UPDATE
 *     $update = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('UPDATE LOW_PRIORITY t SET a = a + 1 WHERE b = 2 ORDER BY a LIMIT 10');
 *     [count($update->statement->assignments), $update->statement->where !== null, $update->toString()] // => [1, true, 'UPDATE LOW_PRIORITY t SET a = a + 1 WHERE b = 2 ORDER BY a LIMIT 10']
 */
final class Update implements Statement
{
    use Snapshot;

    /**
     * @var list<Relation> The table references in written order
     */
    public readonly array $tables;

    /**
     * @var list<Assignment> The assignments in written order
     */
    public readonly array $assignments;

    /**
     * @var list<OrderItem> The ORDER BY items in written order
     */
    public readonly array $orderBy;

    /**
     * @param WithClause|null $with The common tables (MySQL 8.0 and later)
     * @param bool $lowPriority Whether LOW_PRIORITY is written
     * @param bool $ignore Whether IGNORE turns errors into warnings
     * @param list<Relation> $tables The table references; at least one
     * @param list<Assignment> $assignments The assignments; at least one
     * @param Scalar|null $where The row predicate
     * @param list<OrderItem> $orderBy The order the rows are updated in
     * @param Limit|null $limit The most rows updated
     * @throws InvalidConstruction When there is no table or no assignment
     */
    public function __construct(
        public readonly ?WithClause $with,
        public readonly bool $lowPriority,
        public readonly bool $ignore,
        array $tables,
        array $assignments,
        public readonly ?Scalar $where = null,
        array $orderBy = [],
        public readonly ?Limit $limit = null,
    ) {
        $this->tables = Check::listOf($tables, Relation::class, 'UPDATE names at least one table reference.', 1);
        $this->assignments = Check::listOf($assignments, Assignment::class, 'UPDATE holds at least one assignment.', 1);
        $this->orderBy = Check::listOf($orderBy, OrderItem::class, 'ORDER BY holds ordering items.');
    }

    /**
     * Derives the tables, the assignments and the clauses; the statement returns no rows.
     */
    public function deriveStatement(Derivation $derivation): void
    {
        (new ChangeFacts())->update($this, $derivation, $derivation->environment());
    }

    /**
     * Writes the statement.
     */
    public function render(Output $out): void
    {
        $out->node($this->with)->keyword('UPDATE');
        if ($this->lowPriority) {
            $out->keyword('LOW_PRIORITY');
        }
        if ($this->ignore) {
            $out->keyword('IGNORE');
        }
        $out->list($this->tables)->keyword('SET')->list($this->assignments);
        if ($this->where !== null) {
            $out->keyword('WHERE')->node($this->where);
        }
        if ($this->orderBy !== []) {
            $out->keyword('ORDER', 'BY')->list($this->orderBy);
        }
        $out->node($this->limit);
    }
}
