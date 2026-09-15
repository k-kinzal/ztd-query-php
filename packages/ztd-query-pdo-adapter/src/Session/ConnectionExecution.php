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
use ZtdQuery\Platform\SessionFactory;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Session;

/**
 * Coordinates native connection execution with its shadow session.
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
final class ConnectionExecution
{
    private readonly Session $session;

    /**
     * Create a shadow session for the supplied native connection.
     */
    public function __construct(private readonly PDO $pdo, ?ZtdConfig $config = null, ?SessionFactory $factory = null)
    {
        $resolvedFactory = $factory ?? (new DriverSessionFactory())->forConnection($pdo);
        $this->session = $resolvedFactory->create(new PdoConnection($pdo), $config ?? ZtdConfig::default());
    }

    /**
     * Return the wrapped native connection.
     */
    public function native(): PDO
    {
        return $this->pdo;
    }

    /**
     * Return the shadow session shared by all statements on this connection.
     */
    public function session(): Session
    {
        return $this->session;
    }

    /**
     * Prepare native SQL and let the facade wrap the resulting execution plan.
     *
     * @template TOption
     * @param array<TOption> $options Opaque options forwarded to the native driver.
     * @param Closure(PDOStatement, Session, RewritePlan, PreparedQuery, int): PDOStatement $wrap
     * @throws ZtdPdoException When rewriting cannot prepare the statement.
     */
    public function prepare(string $query, array $options, Closure $wrap): PDOStatement|false
    {
        if (!$this->session->isEnabled()) {
            return $this->pdo->prepare($query, $options);
        }

        try {
            $native = $this->pdo;
            $execution = new PreparedQuery($this->session, $query, static fn (string $sql): PDOStatement|false => $native->prepare($sql, $options));
            $plan = $execution->rewrite();
            $compiled = $this->session->parameterBindingCompiler()?->compile($plan->sql(), null);
            $statement = $execution->prepare($compiled['sql'] ?? $plan->sql());
        } catch (DatabaseException $exception) {
            throw new ZtdPdoException($exception->getMessage(), 0, $exception);
        }

        $defaultFetchMode = $this->pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE);

        return $wrap(
            $statement,
            $this->session,
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
        if ($this->session->isEnabled()) {
            $transactionStatement = $this->session->transactionStatement($query);
            if ($transactionStatement !== null) {
                $statement = $this->pdo->query($query, $fetchMode, ...$fetchModeArgs);
                if ($statement !== false) {
                    $this->session->applyTransactionStatement($transactionStatement);
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
        if (!$this->session->isEnabled()) {
            return $this->pdo->exec($statement);
        }

        $statements = $this->session->splitStatements($statement);
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

        $transactionStatement = $this->session->transactionStatement($statement);
        if ($transactionStatement !== null) {
            $result = $this->pdo->exec($statement);
            if ($result !== false) {
                $this->session->applyTransactionStatement($transactionStatement);
            }

            return $result;
        }

        try {
            return $this->session->execStatement($statement);
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
            $this->session->beginTransaction();
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
            $this->session->commitTransaction();
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
            $this->session->rollBackTransaction();
        }

        return $result;
    }

    /**
     * Prefer the shadow-generated key when ZTD handles an unnamed sequence.
     */
    public function lastInsertId(?string $name = null): string|false
    {
        if ($this->session->isEnabled() && $name === null) {
            $lastInsertId = $this->session->lastInsertId();
            if ($lastInsertId !== false) {
                return $lastInsertId;
            }
        }

        return $this->pdo->lastInsertId($name);
    }
}
