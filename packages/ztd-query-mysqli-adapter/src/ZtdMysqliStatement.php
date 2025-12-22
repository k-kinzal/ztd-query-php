<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli_result;
use mysqli_stmt;
use mysqli_warning;
use ZtdQuery\ExecuteResult;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * mysqli_stmt wrapper that applies ZTD rewrite/simulation on execute().
 *
 * Uses delegation pattern: extends mysqli_stmt for type compatibility,
 * but delegates all operations to an inner mysqli_stmt instance.
 *
 * All public methods are explicitly overridden to prevent parent class
 * implementation from being called.
 *
 * Properties are delegated via __get/__isset to the delegate instance.
 */
final class ZtdMysqliStatement extends mysqli_stmt
{
    /**
     * Inner mysqli_stmt to delegate operations to.
     * When ZTD is enabled, this is prepared with the rewritten SQL.
     */
    private mysqli_stmt $delegate;

    /**
     * ZTD session context.
     */
    private Session $session;

    /**
     * Rewrite plan from prepare time (null when ZTD disabled).
     */
    private ?RewritePlan $plan;

    /**
     * Last execution result from Session.
     */
    private ?ExecuteResult $result = null;

    /**
     * Cached mysqli_result from execute (for READ operations).
     */
    private mysqli_result|false|null $cachedMysqliResult = null;

    public function __construct(mysqli_stmt $delegate, Session $session, ?RewritePlan $plan)
    {
        // Do not call parent constructor
        $this->delegate = $delegate;
        $this->session = $session;
        $this->plan = $plan;
    }

    // === Property delegation via __get/__isset ===

    /**
     * Delegate property access to the delegate instance.
     *
     * Handles affected_rows and num_rows specially when ZTD result is available.
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            if ($name === 'affected_rows') {
                return $this->result->rowCount();
            }

            if ($name === 'num_rows') {
                return $this->result->rowCount();
            }

            if ($name === 'insert_id') {
                return $this->delegate->insert_id;
            }
        }

        return $this->delegate->{$name};
    }

    /**
     * Delegate property isset check to the delegate instance.
     */
    public function __isset(string $name): bool
    {
        return isset($this->delegate->{$name});
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
    public function ztdAffectedRows(): int
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            return $this->result->rowCount();
        }

        return (int) $this->delegate->affected_rows;
    }

    // === mysqli_stmt methods - all explicitly overridden for delegation ===

    /**
     * Bind parameters to the statement.
     *
     * @param string $types Type specification string (i=int, d=double, s=string, b=blob)
     * @param mixed ...$vars Variables to bind (by reference)
     */
    public function bind_param(string $types, mixed &...$vars): bool
    {
        return $this->delegate->bind_param($types, ...$vars);
    }

    /**
     * {@inheritDoc}
     */
    public function bind_result(mixed &...$vars): bool
    {
        return $this->delegate->bind_result(...$vars);
    }

    /**
     * Execute the statement, applying ZTD simulation as needed.
     *
     * @param array<int, mixed>|null $params Optional parameters to bind (PHP 8.1+).
     */
    public function execute(?array $params = null): bool
    {
        $this->result = null;

        // No plan means ZTD was disabled at prepare time (shouldn't happen with new design)
        if ($this->plan === null) {
            if ($params !== null) {
                return $this->delegate->execute($params);
            }
            return $this->delegate->execute();
        }

        // SKIPPED: do not execute, return false
        if (!$this->session->shouldExecute($this->plan)) {
            return false;
        }

        // READ: execute directly (includes passthrough cases)
        if (!$this->session->needsPostProcessing($this->plan)) {
            if ($params !== null) {
                return $this->delegate->execute($params);
            }
            return $this->delegate->execute();
        }

        // WRITE_SIMULATED/DDL_SIMULATED: execute and process result
        if ($params !== null) {
            if (!$this->delegate->execute($params)) {
                return false;
            }
        } else {
            if (!$this->delegate->execute()) {
                return false;
            }
        }

        // Get the result set and cache it for later get_result() calls
        $this->cachedMysqliResult = $this->delegate->get_result();

        if ($this->cachedMysqliResult !== false) {
            $this->result = $this->session->processExecutedStatement(
                $this->plan,
                new MysqliResultStatement($this->cachedMysqliResult, $this->delegate->affected_rows)
            );
        } else {
            // No result set (e.g., for WRITE operations that don't return rows)
            $this->result = $this->session->createEmptyWriteResult();
        }

        return $this->result->isSuccess();
    }

    /**
     * {@inheritDoc}
     */
    public function get_result(): mysqli_result|false
    {
        // If we have a cached result from ZTD execution, return it
        if ($this->cachedMysqliResult !== null) {
            $result = $this->cachedMysqliResult;
            // Clear the cache so subsequent calls go to delegate
            $this->cachedMysqliResult = null;

            return $result;
        }

        // For ZTD results without cached mysqli_result (WRITE/DDL operations)
        if ($this->result !== null && !$this->result->isPassthrough()) {
            if (!$this->result->hasResultSet()) {
                return false;
            }
        }

        return $this->delegate->get_result();
    }

    /**
     * {@inheritDoc}
     */
    public function fetch(): ?bool
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            // WRITE/DDL operations don't return result sets in standard mysqli
            if (!$this->result->hasResultSet()) {
                return null;
            }
        }

        // For READ queries: delegate to statement (prepared with rewritten SQL)
        return $this->delegate->fetch();
    }

    /**
     * {@inheritDoc}
     */
    #[\ReturnTypeWillChange]
    public function close()
    {
        $this->delegate->close();
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function free_result(): void
    {
        $this->delegate->free_result();
    }

    /**
     * {@inheritDoc}
     */
    public function reset(): bool
    {
        $this->result = null;

        return $this->delegate->reset();
    }

    /**
     * {@inheritDoc}
     */
    public function store_result(): bool
    {
        return $this->delegate->store_result();
    }

    /**
     * {@inheritDoc}
     */
    public function data_seek(int $offset): void
    {
        $this->delegate->data_seek($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function result_metadata(): mysqli_result|false
    {
        return $this->delegate->result_metadata();
    }

    /**
     * {@inheritDoc}
     */
    public function attr_get(int $attribute): int
    {
        return $this->delegate->attr_get($attribute);
    }

    /**
     * {@inheritDoc}
     */
    public function attr_set(int $attribute, int $value): bool
    {
        return $this->delegate->attr_set($attribute, $value);
    }

    /**
     * {@inheritDoc}
     */
    public function get_warnings(): mysqli_warning|false
    {
        return $this->delegate->get_warnings();
    }

    /**
     * {@inheritDoc}
     */
    public function more_results(): bool
    {
        return $this->delegate->more_results();
    }

    /**
     * {@inheritDoc}
     */
    public function next_result(): bool
    {
        return $this->delegate->next_result();
    }

    /**
     * {@inheritDoc}
     */
    public function num_rows(): int|string
    {
        if ($this->result !== null && !$this->result->isPassthrough()) {
            return $this->result->rowCount();
        }

        return $this->delegate->num_rows();
    }

    /**
     * {@inheritDoc}
     */
    public function prepare(string $query): bool
    {
        return $this->delegate->prepare($query);
    }

    /**
     * {@inheritDoc}
     */
    public function send_long_data(int $param_num, string $data): bool
    {
        return $this->delegate->send_long_data($param_num, $data);
    }
}
