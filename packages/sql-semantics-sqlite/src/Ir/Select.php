<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir;

use SqlSemantics\Platform\Sqlite\Ir\From\Join;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Comparison;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\Logical;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete SQLite projection over a checked list of bound table occurrences.
 *
 * @visibility public
 * @example Constructing a projection directly
 *     $column = new \SqlSemantics\Platform\Sqlite\Ir\ResultColumn(new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('42'), new \SqlSemantics\Platform\Sqlite\Ir\Identifier('answer'));
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Select([$column]))->toString() // => 'SELECT 42 AS "answer"'
 */
final class Select implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<ResultColumn> The ordered result columns
     */
    public readonly array $columns;

    /**
     * @var list<Join> Joined inputs in their associative order
     */
    public readonly array $joins;

    /**
     * @var list<Order> Result ordering keys in their priority order
     */
    public readonly array $orderBy;

    /**
     * Establishes the list and scope conditions before publication.
     *
     * @param non-empty-list<ResultColumn> $columns The ordered result columns
     * @param TableSource|null $source The first external input
     * @param list<Join> $joins The inputs joined to the left prefix
     * @param Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|Parameter|Comparison|Logical|null $where The post-join filter
     * @param list<Order> $orderBy The output ordering
     * @param bool $distinct Whether equivalent result rows are collapsed
     * @param Limit|null $limit The bounds applied after ordering
     */
    public function __construct(
        array $columns,
        public readonly ?TableSource $source = null,
        array $joins = [],
        public readonly Arithmetic|ColumnReference|IntegerLiteral|NullLiteral|Parameter|Comparison|Logical|null $where = null,
        array $orderBy = [],
        public readonly bool $distinct = false,
        public readonly ?Limit $limit = null,
    ) {
        $results = Check::listOf($columns, ResultColumn::class, 'A SELECT needs a nonempty list of result columns.', 1);
        $this->joins = Check::listOf($joins, Join::class, 'Joins must be an ordered list of join steps.');
        $this->orderBy = Check::listOf($orderBy, Order::class, 'Ordering must be an ordered list of output positions.');
        (new SelectBounds())->check($results, $source, $this->joins, $where, $this->orderBy, $limit);
        $this->columns = $results;
    }

    /**
     * Identifies the fixed SQLite language profile.
     */
    public function profileId(): string
    {
        return 'sqlite-3.47.2/utf8/short-column-names/numbered-parameters';
    }

    /**
     * Renders the statement from its semantic structure.
     */
    public function toString(): string
    {
        return (new SelectSql())->render($this);
    }
}
