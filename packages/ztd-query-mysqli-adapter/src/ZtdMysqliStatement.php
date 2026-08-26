<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli;

use mysqli_result;
use mysqli_stmt;
use mysqli_warning;
use Override;
use ReturnTypeWillChange;
use ZtdQuery\Connection\Exception\DatabaseException;
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
final class ZtdMysqliStatement extends MysqliStatementBindingBridge
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
        parent::__construct($delegate);
        $this->delegate = $delegate;
        $this->session = $session;
        $this->plan = $plan;
    }

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

        return match ($name) {
            'affected_rows' => $this->delegate->affected_rows,
            'insert_id' => $this->delegate->insert_id,
            'num_rows' => $this->delegate->num_rows,
            'param_count' => $this->delegate->param_count,
            'field_count' => $this->delegate->field_count,
            'errno' => $this->delegate->errno,
            'error' => $this->delegate->error,
            'error_list' => $this->delegate->error_list,
            'sqlstate' => $this->delegate->sqlstate,
            'id' => $this->delegate->id,
            default => null,
        };
    }

    /**
     * Delegate property isset check to the delegate instance.
     */
    public function __isset(string $name): bool
    {
        return in_array($name, [
            'affected_rows',
            'insert_id',
            'num_rows',
            'param_count',
            'field_count',
            'errno',
            'error',
            'error_list',
            'sqlstate',
            'id',
        ], true);
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

    /**
     * Execute the statement, applying ZTD simulation as needed.
     *
     * @param array<mixed, mixed>|null $params Optional parameters to bind (PHP 8.1+).
     *
     * @throws ZtdMysqliException
     */
    #[Override]
    public function execute(?array $params = null): bool
    {
        $this->result = null;

        if ($this->plan === null) {
            if ($params !== null) {
                return $this->delegate->execute($params);
            }
            return $this->delegate->execute();
        }

        if (!$this->session->shouldExecute($this->plan)) {
            return false;
        }

        if (!$this->session->needsPostProcessing($this->plan)) {
            if ($params !== null) {
                return $this->delegate->execute($params);
            }
            return $this->delegate->execute();
        }

        if ($params !== null) {
            if (!$this->delegate->execute($params)) {
                return false;
            }
        } else {
            if (!$this->delegate->execute()) {
                return false;
            }
        }

        $this->cachedMysqliResult = $this->delegate->get_result();

        if ($this->cachedMysqliResult !== false) {
            try {
                $this->result = $this->session->processExecutedStatement(
                    $this->plan,
                    new MysqliResultStatement($this->cachedMysqliResult, $this->delegate->affected_rows)
                );
            } catch (DatabaseException $e) {
                throw new ZtdMysqliException($e->getMessage(), 0, $e);
            }
        } else {
            $this->result = $this->session->createEmptyWriteResult();
        }

        return $this->result->isSuccess();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_result(): mysqli_result|false
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
     * {@inheritDoc}
     */
    #[Override]
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
     * {@inheritDoc}
     */
    #[Override]
    #[ReturnTypeWillChange]
    public function close()
    {
        $this->delegate->close();
        return true;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function free_result(): void
    {
        $this->delegate->free_result();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function reset(): bool
    {
        $this->result = null;

        return $this->delegate->reset();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function store_result(): bool
    {
        return $this->delegate->store_result();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function data_seek(int $offset): void
    {
        $this->delegate->data_seek($offset);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function result_metadata(): mysqli_result|false
    {
        return $this->delegate->result_metadata();
    }

    /**
     * {@inheritDoc}
     *
     * @throws ZtdMysqliException
     */
    #[Override]
    public function attr_get(int $attribute): int
    {
        $value = $this->delegate->attr_get($attribute);
        if ($value === false) {
            throw new ZtdMysqliException('Unable to read the mysqli statement attribute.');
        }

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function attr_set(int $attribute, int $value): bool
    {
        return $this->delegate->attr_set($attribute, $value);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function get_warnings(): mysqli_warning|false
    {
        return $this->delegate->get_warnings();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function more_results(): bool
    {
        return $this->delegate->more_results();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function next_result(): bool
    {
        return $this->delegate->next_result();
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
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
    #[Override]
    public function prepare(string $query): bool
    {
        return $this->delegate->prepare($query);
    }

    /**
     * {@inheritDoc}
     */
    #[Override]
    public function send_long_data(int $param_num, string $data): bool
    {
        return $this->delegate->send_long_data($param_num, $data);
    }

}
