<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Model\Statement\Snapshot;
use SqlSemantics\SemanticsIR;

/**
 * A complete SQLite compound query evaluated from left to right.
 *
 * @visibility public
 * @example Retaining duplicate rows with UNION ALL
 *     $row = new \SqlSemantics\Platform\Sqlite\Ir\Rows\ValueRow([new \SqlSemantics\Platform\Sqlite\Ir\IntegerLiteral('1')]);
 *     $arm = new \SqlSemantics\Platform\Sqlite\Ir\Rows\ValuesQuery([$row]);
 *     $step = new \SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundStep(\SqlSemantics\Platform\Sqlite\Ir\Rows\CompoundOperator::UnionAll, $arm);
 *     (new \SqlSemantics\Platform\Sqlite\Ir\Rows\Compound($arm, [$step]))->toString() // => 'VALUES (1) UNION ALL VALUES (1)'
 */
final class Compound implements SemanticsIR
{
    use Snapshot;

    /**
     * @var non-empty-list<CompoundStep> The ordered operations and their right arms
     */
    public readonly array $steps;

    /**
     * @var list<Order> The ordering of the combined result
     */
    public readonly array $orderBy;

    /**
     * Checks the shared width and the placement of child and outer clauses.
     *
     * @param list<CompoundStep> $steps The arms after the first
     * @param list<Order> $orderBy The combined result ordering
     */
    public function __construct(public readonly Select|ValuesQuery $first, array $steps, array $orderBy = [], public readonly ?Limit $limit = null)
    {
        $members = Check::listOf($steps, CompoundStep::class, 'A compound requires a second arm.', 1);
        $orders = Check::listOf($orderBy, Order::class, 'A compound requires a list of checked order terms.');
        (new CompoundBounds())->check($first, $members, $orders, $limit);
        $this->steps = $members;
        $this->orderBy = $orders;
    }

    /**
     * Answers the profile shared by the checked arms.
     */
    public function profileId(): string
    {
        return $this->first->profileId();
    }

    /**
     * Emits the arms and operations in lexical order, then the outer clauses.
     */
    public function toString(): string
    {
        $sql = $this->first->toString();
        foreach ($this->steps as $step) {
            $sql .= ' ' . $step->operator->value . ' ' . $step->query->toString();
        }
        $renderer = new SelectSql();

        return $sql . $renderer->ordering($this->orderBy) . ($this->limit === null ? '' : ' ' . $renderer->limit($this->limit));
    }
}
