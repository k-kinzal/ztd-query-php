<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Mysqli\Session;

use Closure;
use mysqli;
use mysqli_result;
use mysqli_stmt;
use ZtdQuery\Adapter\Mysqli\Driver\MysqliConnection;
use ZtdQuery\Adapter\Mysqli\ZtdMysqliException;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Platform\MySql\MySqlSessionFactory;
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;
use ZtdQuery\Sql\TransactionStatement;

/**
 * Coordinates native connection execution with its shadow session.
 *
 * @visibility ZtdQuery\Adapter\Mysqli
 */
final class ConnectionExecution
{
    private readonly Session $session;

    private ?int $ztdAffectedRowCount = null;

    /**
     * Create a shadow session for the supplied native connection.
     */
    public function __construct(private readonly mysqli $mysqli, ?ZtdConfig $config = null, ?SessionFactory $factory = null)
    {
        $resolvedFactory = $factory ?? new MySqlSessionFactory();
        $this->session = $resolvedFactory->create(new MysqliConnection($mysqli), $config ?? ZtdConfig::default());
    }

    /**
     * Return the wrapped native connection.
     */
    public function native(): mysqli
    {
        return $this->mysqli;
    }

    /**
     * Return the session shared by statements on this connection.
     */
    public function session(): Session
    {
        return $this->session;
    }

    /**
     * Return the simulated count when native affected_rows must be overridden.
     */
    public function simulatedAffectedRows(): ?int
    {
        return $this->ztdAffectedRowCount;
    }

    /**
     * Prepare rewritten SQL and let the facade wrap the native statement.
     *
     * @param Closure(mysqli_stmt, Session, RewritePlan): mysqli_stmt $wrap
     *
     * @throws ZtdMysqliException When ZTD-specific exception occurs (wraps DatabaseException).
     */
    public function prepare(string $query, Closure $wrap): mysqli_stmt|false
    {
        if (!$this->session->isEnabled()) {
            return $this->mysqli->prepare($query);
        }

        try {
            $plan = $this->session->rewrite($query);
        } catch (DatabaseException $e) {
            throw new ZtdMysqliException($e->getMessage(), 0, $e);
        }

        $stmt = $this->mysqli->prepare($plan->sql());
        if ($stmt === false) {
            return false;
        }

        return $wrap($stmt, $this->session, $plan);
    }

    /**
     * Synchronize transaction queries before dispatching statement execution.
     *
     * @param Closure(string): (mysqli_result|bool) $execute
     *
     */
    public function query(string $query, int $resultMode, Closure $execute): mysqli_result|bool
    {
        if (!$this->session->isEnabled()) {
            $this->ztdAffectedRowCount = null;
            return $this->mysqli->query($query, $resultMode);
        }

        $transactionStatement = $this->session->transactionStatement($query);
        if ($transactionStatement !== null) {
            $result = $this->mysqli->query($query, $resultMode);
            if ($result !== false) {
                $this->session->applyTransactionStatement($transactionStatement);
            }

            return $result;
        }

        return $execute($query);
    }

    /**
     * Apply transaction state after successful native execution.
     *
     * @param Closure(string): (mysqli_stmt|false) $prepare
     *
     */
    public function realQuery(string $query, Closure $prepare): bool
    {
        if (!$this->session->isEnabled()) {
            return $this->mysqli->real_query($query);
        }

        $transactionStatement = $this->session->transactionStatement($query);
        if ($transactionStatement !== null) {
            $result = $this->mysqli->real_query($query);
            if ($result) {
                $this->session->applyTransactionStatement($transactionStatement);
            }

            return $result;
        }

        $stmt = $prepare($query);
        return $stmt !== false && $stmt->execute();
    }

    /**
     * Synchronize native execution with shadow transaction state.
     */
    public function beginTransaction(int $flags = 0, ?string $name = null): bool
    {
        $result = $this->mysqli->begin_transaction($flags, $name);
        if ($result) {
            $this->session->beginTransaction();
        }

        return $result;
    }

    /**
     * Synchronize native execution with shadow transaction state.
     */
    public function commit(int $flags = 0, ?string $name = null): bool
    {
        $result = $this->mysqli->commit($flags, $name);
        if ($result) {
            $this->session->commitTransaction();
        }

        return $result;
    }

    /**
     * Synchronize native execution with shadow transaction state.
     */
    public function rollBack(int $flags = 0, ?string $name = null): bool
    {
        $result = $this->mysqli->rollback($flags, $name);
        if ($result) {
            $this->session->rollBackTransaction();
        }

        return $result;
    }

    /**
     * Synchronize native execution with shadow transaction state.
     */
    public function autocommit(bool $enable): bool
    {
        $result = $this->mysqli->autocommit($enable);
        if ($result) {
            if ($enable) {
                $this->session->commitTransaction();
            } else {
                $this->session->beginTransaction();
            }
        }

        return $result;
    }

    /**
     * Synchronize native execution with shadow transaction state.
     */
    public function releaseSavepoint(string $name): bool
    {
        $result = $this->mysqli->release_savepoint($name);
        if ($result) {
            $this->session->applyTransactionStatement(TransactionStatement::release($name));
        }

        return $result;
    }

    /**
     * Synchronize native execution with shadow transaction state.
     */
    public function savepoint(string $name): bool
    {
        $result = $this->mysqli->savepoint($name);
        if ($result) {
            $this->session->applyTransactionStatement(TransactionStatement::savepoint($name));
        }

        return $result;
    }

    /**
     * Execute through the facade while retaining the simulated affected-row count.
     *
     * @param Closure(string): (mysqli_stmt|false) $prepare
     * @param Closure(mysqli_stmt): ?int $affectedRows
     *
     * @template TParameter
     * @param array<TParameter>|null $params
     */
    public function executeQuery(string $query, ?array $params, Closure $prepare, Closure $affectedRows): mysqli_result|bool
    {
        $stmt = $prepare($query);
        if ($stmt === false || !$stmt->execute($params)) {
            return false;
        }
        $this->ztdAffectedRowCount = $affectedRows($stmt);
        $result = $stmt->get_result();
        return $result === false ? true : $result;
    }

    /**
     * Get the affected row count from the last ZTD or regular operation.
     *
     * Note: Direct property access ($this->affected_rows) is not supported
     * because PHP's C extension property handler for mysqli takes precedence
     * over __get when the parent constructor was not called. Use this method instead.
     */
    public function affectedRows(): int
    {
        if ($this->ztdAffectedRowCount !== null) {
            return $this->ztdAffectedRowCount;
        }

        return (int) $this->mysqli->affected_rows;
    }
}
