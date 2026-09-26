<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli\Session;

use mysqli_result;
use mysqli_stmt;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;
use ZtdQuery\ExecuteResult;
use ZtdQuery\QueryExecutor;
use ZtdQuery\Rewrite\RewritePlan;

/**
 * Coordinates native execution and shadow post-processing for a prepared statement.
 *
 * @visibility ZtdQuery\Adapter\Mysqli
 */
final class StatementExecution
{
    private ?ExecuteResult $result = null;

    private mysqli_result|false|null $cachedMysqliResult = null;

    /**
     * Retain the native statement and its shadow execution plan.
     */
    public function __construct(private readonly mysqli_stmt $delegate, private readonly QueryExecutor $executor, private readonly ?RewritePlan $plan)
    {
    }

    /**
     * Return the native statement.
     */
    public function native(): mysqli_stmt
    {
        return $this->delegate;
    }

    /**
     * Return the result of shadow post-processing.
     */
    public function result(): ?ExecuteResult
    {
        return $this->result;
    }

    /**
     * Get affected rows for ZTD results.
     *
     * This method exists because mysqli_stmt's C extension property handler
     * takes precedence over __get, making $stmt->affected_rows inaccessible
     * when the parent constructor was not called. This provides a safe
     * alternative for ZtdMysqli to query affected rows after execution.
     *
     * @return int The number of affected rows from ZTD processing, or from the delegate.
     */
    public function affectedRows(): int
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            return $this->result->rowCount();
        }

        return (int) $this->delegate->affected_rows;
    }

    /**
     * Execute the statement, applying ZTD simulation as needed.
     *
     * @template TParameter
     * @param array<TParameter>|null $params Optional parameters to bind (PHP 8.1+).
     * @throws ZtdMysqliException When the session cannot process the native result.
     */
    public function execute(?array $params = null): bool
    {
        $this->result = null;
        if ($this->plan !== null && !$this->executor->shouldExecute($this->plan)) {
            return false;
        }
        if ($this->plan === null || !$this->executor->needsPostProcessing($this->plan)) {
            return $this->delegate->execute($params);
        }
        if (!$this->delegate->execute($params)) {
            return false;
        }
        $this->cachedMysqliResult = $this->delegate->get_result();
        $this->result = (new MysqliResultProcessor())->process($this->executor, $this->plan, $this->cachedMysqliResult, $this->delegate->affected_rows);
        return $this->result->isSuccess();
    }

    /**
     * Coordinate the native statement with its simulated result.
     */
    public function getResult(): mysqli_result|false
    {
        if ($this->cachedMysqliResult !== null) {
            $result = $this->cachedMysqliResult;
            $this->cachedMysqliResult = null;

            return $result;
        }

        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return false;
            }
        }

        return $this->delegate->get_result();
    }

    /**
     * Coordinate the native statement with its simulated result.
     */
    public function fetch(): ?bool
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return null;
            }
        }

        return $this->delegate->fetch();
    }

    /**
     * Coordinate the native statement with its simulated result.
     */
    public function reset(): bool
    {
        $this->result = null;

        return $this->delegate->reset();
    }
}
