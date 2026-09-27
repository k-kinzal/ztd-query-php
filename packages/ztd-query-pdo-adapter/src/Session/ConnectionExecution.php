<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use Closure;
use PDO;
use PDOStatement;
use ZtdQuery\Adapter\Pdo\Driver\PdoConnection;
use ZtdQuery\Adapter\Pdo\ZtdPdoException;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Platform;
use ZtdQuery\QueryExecutor;
use ZtdQuery\Rewrite\RewritePlan;

/**
 * Coordinates native connection execution with its shadow session.
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
final class ConnectionExecution
{
    private readonly QueryExecutor $executor;

    /**
     * Create a shadow session for the supplied native connection.
     */
    public function __construct(private readonly PDO $pdo, ?ZtdConfig $config = null, ?Platform $platform = null)
    {
        $resolvedPlatform = $platform ?? (new DriverPlatform())->forConnection($pdo);
        $this->executor = new QueryExecutor(new PdoConnection($pdo), $resolvedPlatform, $config ?? ZtdConfig::default());
    }

    /**
     * Return the wrapped native connection.
     */
    public function native(): PDO
    {
        return $this->pdo;
    }

    /**
     * Return the core executor shared by all statements on this connection.
     */
    public function executor(): QueryExecutor
    {
        return $this->executor;
    }

    /**
     * Prepare native SQL and let the facade wrap the resulting execution plan.
     *
     * @template TOption
     * @param array<TOption> $options Opaque options forwarded to the native driver.
     * @param Closure(PDOStatement, QueryExecutor, RewritePlan, PreparedQuery, int): PDOStatement $wrap
     * @throws ZtdPdoException When rewriting cannot prepare the statement.
     */
    public function prepare(string $query, array $options, Closure $wrap): PDOStatement|false
    {
        if (!$this->executor->session()->isEnabled()) {
            return $this->pdo->prepare($query, $options);
        }

        try {
            $native = $this->pdo;
            $execution = new PreparedQuery($this->executor, $query, static fn (string $sql): PDOStatement|false => $native->prepare($sql, $options));
            $plan = $execution->rewrite();
            $compiled = $this->executor->platform()->parameterBindingCompiler()?->compile($plan->sql(), null);
            $statement = $execution->prepare($compiled['sql'] ?? $plan->sql());
        } catch (DatabaseException $exception) {
            throw new ZtdPdoException($exception->getMessage(), 0, $exception);
        }

        $defaultFetchMode = $this->pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);

        return $wrap(
            $statement,
            $this->executor,
            $plan,
            $execution,
            is_int($defaultFetchMode) ? $defaultFetchMode : PDO::FETCH_BOTH,
        );
    }

    /**
     * Execute a query through the facade's preparation dispatch.
     *
     * @template TFetchArgument
     * @param array<TFetchArgument> $fetchModeArgs Native fetch options.
     * @param Closure(string): (PDOStatement|false) $prepare
     */
    public function query(string $query, ?int $fetchMode, array $fetchModeArgs, Closure $prepare): PDOStatement|false
    {
        if ($this->executor->session()->isEnabled()) {
            $transactionStatement = $this->executor->transactionStatement($query);
            if ($transactionStatement !== null) {
                $statement = $this->pdo->query($query, $fetchMode, ...$fetchModeArgs);
                if ($statement !== false) {
                    $this->executor->session()->applyTransactionStatement($transactionStatement);
                }

                return $statement;
            }
        }

        $stmt = $prepare($query);
        if ($stmt === false) {
            return false;
        }

        if ($fetchMode !== null) {
            $stmt->setFetchMode($fetchMode, ...$fetchModeArgs);
        }

        if (!$stmt->execute()) {
            return false;
        }

        return $stmt;
    }

    /**
     * Execute statements and synchronize native transaction and shadow state.
     *
     * @param Closure(string): (int|false) $execute Preserve facade dispatch for each batch statement.
     * @throws ZtdPdoException When shadow execution rejects the SQL.
     */
    public function exec(string $statement, Closure $execute): int|false
    {
        if (!$this->executor->session()->isEnabled()) {
            return $this->pdo->exec($statement);
        }

        $statements = $this->executor->splitStatements($statement);
        if (count($statements) > 1) {
            $affectedRows = 0;
            foreach ($statements as $one) {
                $result = $execute($one);
                if ($result === false) {
                    return false;
                }
                $affectedRows = $result;
            }

            return $affectedRows;
        }

        $transactionStatement = $this->executor->transactionStatement($statement);
        if ($transactionStatement !== null) {
            $result = $this->pdo->exec($statement);
            if ($result !== false) {
                $this->executor->session()->applyTransactionStatement($transactionStatement);
            }

            return $result;
        }

        try {
            return $this->executor->execStatement($statement);
        } catch (DatabaseException $e) {
            throw new ZtdPdoException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Begin the native transaction before opening a shadow transaction.
     */
    public function beginTransaction(): bool
    {
        $result = $this->pdo->beginTransaction();
        if ($result) {
            $this->executor->session()->beginTransaction();
        }

        return $result;
    }

    /**
     * Commit the shadow transaction after the native commit succeeds.
     */
    public function commit(): bool
    {
        $result = $this->pdo->commit();
        if ($result) {
            $this->executor->session()->commitTransaction();
        }

        return $result;
    }

    /**
     * Roll back shadow state after the native rollback succeeds.
     */
    public function rollBack(): bool
    {
        $result = $this->pdo->rollBack();
        if ($result) {
            $this->executor->session()->rollBackTransaction();
        }

        return $result;
    }

    /**
     * Prefer the shadow-generated key when ZTD handles an unnamed sequence.
     */
    public function lastInsertId(?string $name = null): string|false
    {
        if ($this->executor->session()->isEnabled() && $name === null) {
            $lastInsertId = $this->executor->session()->lastInsertId();
            if ($lastInsertId !== false) {
                return $lastInsertId;
            }
        }

        return $this->pdo->lastInsertId($name);
    }
}
