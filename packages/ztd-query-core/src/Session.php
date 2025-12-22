<?php

declare(strict_types=1);

namespace ZtdQuery;

use RuntimeException;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Aggregates ZTD session state and core collaborators.
 */
final class Session
{
    /**
     * SQL rewrite pipeline implementation.
     *
     * @var SqlRewriter
     */
    private SqlRewriter $rewriter;

    /**
     * In-memory shadow store used by applied mutations.
     *
     * @var ShadowStore
     */
    private ShadowStore $shadowStore;

    /**
     * Executes result-select SQL on the database.
     *
     * @var ResultSelectRunner
     */
    private ResultSelectRunner $resultSelectRunner;

    /**
     * ZTD configuration for error handling behavior.
     *
     * @var ZtdConfig
     */
    private ZtdConfig $config;

    /**
     * Database connection for query execution.
     *
     * @var ConnectionInterface
     */
    private ConnectionInterface $connection;

    /**
     * Whether ZTD mode is enabled for this session.
     *
     * @var bool
     */
    private bool $enabled = true;

    /**
     * @param SqlRewriter $rewriter Rewrite pipeline for SQL.
     * @param ShadowStore $shadowStore Target shadow store for mutation application.
     * @param ResultSelectRunner $resultSelectRunner Executes result-select queries.
     * @param ZtdConfig $config ZTD configuration for error handling.
     * @param ConnectionInterface $connection Database connection for query execution.
     */
    public function __construct(
        SqlRewriter $rewriter,
        ShadowStore $shadowStore,
        ResultSelectRunner $resultSelectRunner,
        ZtdConfig $config,
        ConnectionInterface $connection
    ) {
        $this->rewriter = $rewriter;
        $this->shadowStore = $shadowStore;
        $this->resultSelectRunner = $resultSelectRunner;
        $this->config = $config;
        $this->connection = $connection;
    }

    /**
     * Whether the plan's SQL should be executed against the database.
     */
    public function shouldExecute(RewritePlan $plan): bool
    {
        return $plan->kind() !== QueryKind::SKIPPED;
    }

    /**
     * Whether the plan requires post-execution processing via processExecutedStatement().
     */
    public function needsPostProcessing(RewritePlan $plan): bool
    {
        return $plan->kind() === QueryKind::WRITE_SIMULATED
            || $plan->kind() === QueryKind::DDL_SIMULATED;
    }

    /**
     * Create an empty write-simulated result for skipped writes.
     */
    public function createEmptyWriteResult(): ExecuteResult
    {
        return GenericExecuteResult::fromBufferedRows([], QueryKind::WRITE_SIMULATED);
    }

    /**
     * Enable ZTD behavior for this session.
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable ZTD behavior for this session.
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Check whether ZTD mode is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }


    /**
     * Rewrite SQL using the configured rewriter.
     *
     * Catches exceptions from the rewriter and handles them based on config.
     * For ignore/notice modes, returns a passthrough plan (READ with original SQL).
     *
     * @throws DatabaseException When config is Exception mode and rewrite fails.
     */
    public function rewrite(string $sql): RewritePlan
    {
        try {
            return $this->rewriter->rewrite($sql);
        } catch (UnsupportedSqlException $e) {
            $behavior = $this->config->resolveUnsupportedBehavior($sql);

            if ($behavior === UnsupportedSqlBehavior::Exception) {
                throw new DatabaseException($e->getMessage(), null, 0, $e);
            }

            if ($behavior === UnsupportedSqlBehavior::Notice) {
                trigger_error(
                    sprintf('[ZTD Notice] Unsupported SQL ignored: %s', $sql),
                    E_USER_NOTICE
                );
            }

            return new RewritePlan($sql, QueryKind::SKIPPED);
        } catch (UnknownSchemaException $e) {
            $behavior = $this->config->unknownSchemaBehavior();

            if ($behavior === UnknownSchemaBehavior::Exception) {
                throw new DatabaseException($e->getMessage(), null, 0, $e);
            }

            if ($behavior === UnknownSchemaBehavior::Passthrough) {
                return new RewritePlan($sql, QueryKind::READ);
            }

            if ($behavior === UnknownSchemaBehavior::Notice) {
                trigger_error(
                    sprintf('[ZTD Notice] Unknown table referenced: %s', $e->getIdentifier()),
                    E_USER_NOTICE
                );
            }

            return new RewritePlan('SELECT 1 WHERE FALSE', QueryKind::READ);
        }
    }

    /**
     * Process an already-executed statement based on the rewrite plan.
     *
     * This method handles post-execution logic like shadow application for write queries.
     * Use this when you need to control statement preparation and execution externally.
     *
     * @param RewritePlan $plan The rewrite plan from rewrite().
     * @param StatementInterface $statement The already-executed statement.
     * @return ExecuteResult The execution result.
     */
    public function processExecutedStatement(RewritePlan $plan, StatementInterface $statement): ExecuteResult
    {
        if ($plan->kind() === QueryKind::READ) {
            return GenericExecuteResult::fromStatement($statement, QueryKind::READ);
        }

        $rows = $statement->fetchAll();

        $mutation = $plan->mutation();
        if ($mutation === null) {
            throw new RuntimeException('ZTD Write Protection: Missing shadow mutation for write simulation.');
        }
        $mutation->apply($this->shadowStore, $rows);

        return GenericExecuteResult::fromBufferedRows($rows, QueryKind::WRITE_SIMULATED);
    }

    /**
     * Run result-select query and apply shadow mutation.
     *
     * This method executes a result-select query using the provided executor,
     * then applies the mutation from the rewrite plan to the shadow store.
     *
     * @param RewritePlan $plan The rewrite plan containing the SQL and mutation.
     * @param callable(string): (StatementInterface|false) $executor Function to execute SQL.
     * @return array<int, array<string, mixed>> The affected rows.
     * @throws RuntimeException If the plan has no mutation.
     */
    public function runResultSelectAndApplyShadow(RewritePlan $plan, callable $executor): array
    {
        $mutation = $plan->mutation();
        if ($mutation === null) {
            throw new RuntimeException('ZTD Write Protection: Missing shadow mutation for write simulation.');
        }

        $rows = $this->resultSelectRunner->run($plan->sql(), $executor);
        $mutation->apply($this->shadowStore, $rows);

        return $rows;
    }

    /**
     * Execute an exec-style statement with ZTD rewriting and shadow application.
     *
     * @param string $sql The original SQL statement.
     * @return int|false The number of affected rows, or false on failure.
     * @throws DatabaseException When config is Exception mode and rewrite fails.
     */
    public function execStatement(string $sql): int|false
    {
        $plan = $this->rewrite($sql);

        if ($plan->kind() === QueryKind::SKIPPED) {
            return 0;
        }

        if ($plan->kind() === QueryKind::READ) {
            $stmt = $this->connection->query($plan->sql());
            if ($stmt === false) {
                return false;
            }
            return $stmt->rowCount();
        }

        $mutation = $plan->mutation();
        if ($mutation === null) {
            throw new RuntimeException('ZTD Write Protection: Missing shadow mutation for write simulation.');
        }

        $rows = $this->resultSelectRunner->run($plan->sql(), fn (string $s) => $this->connection->query($s));
        $mutation->apply($this->shadowStore, $rows);

        return count($rows);
    }
}
