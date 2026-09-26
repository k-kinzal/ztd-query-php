<?php

declare(strict_types=1);

namespace ZtdQuery\Simulator;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\QueryExecutor;
use ZtdQuery\Rewrite\QueryKind;

/**
 * Executes rewritten statements and applies shadow mutations for exec().
 *
 * Exception handling for unsupported SQL and unknown schema is now done in QueryExecutor::rewrite(),
 * so this class simply delegates to the core executor.
 */
final class StatementSimulator
{
    /**
     * Core executor for rewrite and mutation application.
     *
     * @var QueryExecutor
     */
    private QueryExecutor $executor;

    /**
     * @param QueryExecutor $executor Core execution pipeline.
     */
    public function __construct(QueryExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Simulate exec() by running result-select and updating shadow state.
     *
     * QueryExecutor::rewrite() now handles exceptions for unsupported SQL and unknown schema
     * based on config, so we no longer need to handle FORBIDDEN/UNKNOWN_SCHEMA here.
     *
     * @param callable(string): (StatementInterface|false) $executor
     */
    public function simulate(string $statement, callable $executor): int|false
    {
        $plan = $this->executor->rewrite($statement);

        if ($plan->kind() === QueryKind::SKIPPED) {
            return 0;
        }

        if ($plan->kind() === QueryKind::READ) {
            $stmt = $executor($plan->sql());
            if ($stmt === false) {
                return false;
            }
            return $stmt->rowCount();
        }

        $rows = $this->executor->runResultSelectAndApplyShadow($plan, $executor);

        return count($rows);
    }
}
