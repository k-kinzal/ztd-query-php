<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Explain;

use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Iterator\Builder;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Query\ExplicitTable;
use SqlSemantics\Platform\MySql\Statement\QueryRoot;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain;
use SqlSemantics\Statement\Operation;

/**
 * Executes an explained TABLE scan and reports the memory iterator's measurements.
 * Cost is zero because memory has no storage cost model. Row counts and elapsed
 * milliseconds come from the executed iterator, including an empty scan.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/explain.html.
 * @visibility MySqlMemory
 */
final class MeasuredTable
{
    /**
     * Reports a single TABLE scan; other plan shapes retain their own reporting path.
     */
    public function report(Operation $operation, Session $session, Context $context, Connection $connection): ?string
    {
        $explain = $operation->statement;
        if (!$explain instanceof Explain || !$explain->analyze || !$explain->statement instanceof QueryRoot || !$explain->statement->query instanceof ExplicitTable) {
            return null;
        }
        $query = $explain->statement->query;
        $planner = new Planner($query, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $iterator = (new Builder())->build($planner->query($query, null)->root);
        $rows = 0;
        $first = null;
        $start = hrtime(true);
        $iterator->init(new Frame($context));
        while ($iterator->read() !== null) {
            $first ??= (hrtime(true) - $start) / 1_000_000;
            $rows++;
        }
        $last = (hrtime(true) - $start) / 1_000_000;
        return '-> Table scan on ' . $query->table->name->value . '  (cost=0 rows=' . $rows . ') (actual time=' . sprintf('%.6g', $first ?? $last) . '..' . sprintf('%.6g', $last) . ' rows=' . $rows . " loops=1)\n";
    }
}
