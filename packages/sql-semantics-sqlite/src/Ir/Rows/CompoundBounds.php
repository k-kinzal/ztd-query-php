<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Rows;

use SqlSemantics\Platform\Sqlite\Ir\Ordering\Limit;
use SqlSemantics\Platform\Sqlite\Ir\Ordering\Order;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ExpressionScope;
use SqlSemantics\Platform\Sqlite\Ir\Select;
use SqlSemantics\Platform\Sqlite\Ir\SelectSql;
use SqlSemantics\Platform\Sqlite\Model\Diagnostic\Check;

/**
 * Checks the statement-wide rules that apply when checked arms are placed together.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class CompoundBounds
{
    private const MAX_SQL_BYTES = 1000000000;

    /**
     * Checks arm positions, width and the length of the emitted query.
     *
     * @param non-empty-list<CompoundStep> $steps
     * @param int $prefixBytes The containing declaration bytes, when any
     * @param list<Order> $orderBy
     */
    public function check(Select|ValuesQuery $first, array $steps, array $orderBy, ?Limit $limit, int $prefixBytes = 0): void
    {
        Check::input(count($steps) < 500, 'A compound can have at most 500 arms.');
        $width = null;
        $bytes = $prefixBytes;
        $arms = [$first];
        foreach ($steps as $step) {
            $arms[] = $step->query;
            $bytes += 2 + strlen($step->operator->value);
        }
        foreach ($arms as $arm) {
            if ($arm instanceof Select) {
                Check::input((new LocalInputs())->occurrences($arm->source, $arm->joins) === [], 'A compound arm cannot contain WITH.');
                Check::input($arm->orderBy === [] && $arm->limit === null, 'Compound ORDER BY and LIMIT belong to the outer query.');
            }
            $armWidth = $arm instanceof Select ? count($arm->columns) : count($arm->rows[0]->values);
            $width ??= $armWidth;
            Check::input($armWidth === $width, 'Compound arms must have the same width.');
            $bytes += strlen($arm->toString());
            Check::input($bytes <= self::MAX_SQL_BYTES, 'Compound exceeds the SQLite statement size budget.');
        }
        $last = $steps[count($steps) - 1]->query;
        Check::input($last instanceof Select || ($orderBy === [] && $limit === null), 'Compound ORDER BY or LIMIT requires a final SELECT arm.');
        Check::input(count($orderBy) <= 2000, 'A compound can have at most 2000 ordering terms.');
        foreach ($orderBy as $order) {
            Check::input($order->position <= $width, 'Compound order position must belong to the output.');
        }

        $renderer = new SelectSql();
        $bytes += strlen($renderer->ordering($orderBy));
        if ($limit !== null) {
            $operands = [[$limit->count, 0, 0]];
            if ($limit->offset !== null) {
                $operands[] = [$limit->offset, 0, 0];
            }
            (new ExpressionScope())->check([], $operands);
            $bytes += 1 + strlen($renderer->limit($limit));
        }
        Check::input($bytes <= self::MAX_SQL_BYTES, 'Compound exceeds the SQLite statement size budget.');
    }
}
