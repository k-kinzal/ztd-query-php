<?php

declare(strict_types=1);

namespace ZtdQuery\Simulator;

use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Session;

/**
 * Executes rewritten statements and applies shadow mutations for exec().
 *
 * Exception handling for unsupported SQL and unknown schema is now done in Session::rewrite(),
 * so this class simply delegates to the session.
 */
final class StatementSimulator
{
    /**
     * Session context for rewrite and mutation application.
     *
     * @var Session
     */
    private Session $session;

    /**
     * @param Session $session Current ZTD session.
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Simulate exec() by running result-select and updating shadow state.
     *
     * Session::rewrite() now handles exceptions for unsupported SQL and unknown schema
     * based on config, so we no longer need to handle FORBIDDEN/UNKNOWN_SCHEMA here.
     *
     * @param callable(string): (StatementInterface|false) $executor
     */
    public function simulate(string $statement, callable $executor): int|false
    {
        $plan = $this->session->rewrite($statement);

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

        $rows = $this->session->runResultSelectAndApplyShadow($plan, $executor);

        return count($rows);
    }
}
