<?php

declare(strict_types=1);

namespace ZtdQuery;

use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Connection\ConnectionInterface;
use ZtdQuery\Connection\Exception\DatabaseException;
use ZtdQuery\Connection\ResultSet;
use ZtdQuery\Connection\StatementInterface;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Platform\CopyTarget;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Shadow\Mutation\MutationImpact;
use ZtdQuery\Shadow\Mutation\ShadowMutation;
use ZtdQuery\Shadow\ReferentialIntegrityEnforcer;
use ZtdQuery\Shadow\ShadowApplication;
use ZtdQuery\Sql\TransactionStatement;

/**
 * Rewrites and executes SQL against one session using an injected database platform.
 *
 * The executor owns execution policy. Session owns mutable virtual state, and
 * Platform supplies database semantics without creating or owning sessions.
 *
 * @visibility public
 * @example Accept database semantics through dependency injection
 *     $connect = static fn (\ZtdQuery\Connection\ConnectionInterface $connection, \ZtdQuery\Platform $platform): \ZtdQuery\QueryExecutor => new \ZtdQuery\QueryExecutor($connection, $platform);
 *     $connect instanceof \Closure // => true
 * @phpstan-import-type Row from StatementInterface
 */
final class QueryExecutor
{
    private readonly Session $session;
    private readonly SqlRewriter $rewriter;
    private readonly ShadowApplication $shadowApplication;
    private readonly RewriteRefusal $refusals;

    /**
     * Reflect a fresh catalog unless the caller supplies virtual session state.
     * Reflection failures propagate before the executor can be used.
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly Platform $platform,
        ZtdConfig $config = new ZtdConfig(),
        ?Session $session = null,
        private readonly ResultSelectRunner $resultSelectRunner = new ResultSelectRunner(),
    ) {
        $this->session = $session ?? new Session(
            registry: $platform->reflectSchema($connection),
            views: $platform->reflectViews($connection),
        );
        $this->rewriter = $platform->createRewriter($this->session->store(), $this->session->registry(), $this->session->views());
        $this->shadowApplication = new ShadowApplication(
            $this->session->store(),
            new ReferentialIntegrityEnforcer($this->session->registry()),
            $this->session->registry(),
            $this->rewriter,
        );
        $this->refusals = new RewriteRefusal($config);
    }

    /**
     * Return the virtual state shared by statements executed through this executor.
     */
    public function session(): Session
    {
        return $this->session;
    }

    /**
     * Return the injected database semantics and optional capabilities.
     */
    public function platform(): Platform
    {
        return $this->platform;
    }

    /**
     * Writes a mutation into the shadow, and remembers the identity it produced.
     *
     * @param ShadowMutation $mutation Mutation to write
     * @param ResultSet $resultSet What the rewritten statement read back
     * @param string $sql Statement being simulated, for the refusal
     *
     * @return MutationImpact What the statement came to
     *
     * @throws DatabaseException When the shadow refuses the statement
     */
    public function applyShadow(ShadowMutation $mutation, ResultSet $resultSet, string $sql): MutationImpact
    {
        $impact = $this->shadowApplication->apply($mutation, $resultSet, $sql);
        $this->session->rememberInsertId($this->shadowApplication->lastInsertIdOf($mutation, $impact));

        return $impact;
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
     * Answers the transaction statement a statement is, if it is one.
     *
     * @param string $sql Statement as it was written
     *
     * @return TransactionStatement|null What it does to the transaction, or null when it is not one
     */
    public function transactionStatement(string $sql): ?TransactionStatement
    {
        return $this->rewriter->transactionStatement($sql);
    }

    /**
     * Answers what a COPY statement is written against, where everything it needs is known.
     *
     * @param string $relation Relation as the statement named it
     * @param string|null $fields Column list as the statement wrote it, or null for every column
     *
     * @return CopyTarget|null The target, or null when the dialect has no COPY or the table is undescribed
     */
    public function copyTarget(string $relation, ?string $fields): ?CopyTarget
    {
        $support = $this->platform->copySupport();
        if ($support === null) {
            return null;
        }
        $definition = $this->session->tableDefinition($support->tableName($relation));
        if ($definition === null) {
            return null;
        }

        return $support->target($relation, $fields, $definition);
    }

    /**
     * Rewrite SQL using the configured rewriter.
     *
     * Applies the configured refusal policy to unsupported SQL and unknown schemas.
     *
     * @throws DatabaseException When config is Exception mode and rewrite fails.
     */
    public function rewrite(string $sql): RewritePlan
    {
        try {
            return $this->rewriter->rewrite($sql);
        } catch (UnsupportedSqlException $e) {
            return $this->refusals->forUnsupported($e, $sql);
        } catch (UnknownSchemaException $e) {
            return $this->refusals->forUnknownSchema($e, $sql, $this->rewriter->emptyResultSelect());
        }
    }

    /**
     * @return list<string>
     */
    public function splitStatements(string $sql): array
    {
        return $this->rewriter->splitStatements($sql);
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
     *
     * @throws DatabaseException When the shadow refuses the statement
     */
    public function processExecutedStatement(RewritePlan $plan, StatementInterface $statement): ExecuteResult
    {
        if ($plan->kind() === QueryKind::READ) {
            return GenericExecuteResult::fromStatement($statement, QueryKind::READ);
        }

        $resultSet = $this->resultSelectRunner->readResultSet($statement, $this->platform->resultColumnTypeResolver());
        $rows = $resultSet->rows;

        $mutation = $plan->requireMutation();
        $impact = $this->applyShadow($mutation, $resultSet, $plan->sql());
        $returningProjection = $plan->returningProjection();
        $resultRows = $returningProjection !== null
            ? $returningProjection->project($impact->returningRows())
            : $rows;

        return GenericExecuteResult::fromBufferedRows(
            $resultRows,
            QueryKind::WRITE_SIMULATED,
            $impact->affectedRowCount($plan->affectedRowsMode()),
            $returningProjection !== null,
        );
    }

    /**
     * Run result-select query and apply shadow mutation.
     *
     * This method executes a result-select query using the provided executor,
     * then applies the mutation from the rewrite plan to the shadow store.
     *
     * @param RewritePlan $plan The rewrite plan containing the SQL and mutation.
     * @param callable(string): (StatementInterface|false) $executor Function to execute SQL.
     * @return array<int, Row> The affected rows.
     * @throws UnsupportedSqlException When the plan carries no mutation to write.
     *
     * @throws DatabaseException When the shadow refuses the statement
     */
    public function runResultSelectAndApplyShadow(RewritePlan $plan, callable $executor): array
    {
        $mutation = $plan->requireMutation();

        $resultSet = $this->resultSelectRunner->runResultSet(
            $plan->sql(),
            $executor,
            $this->platform->resultColumnTypeResolver(),
        );
        $this->applyShadow($mutation, $resultSet, $plan->sql());

        return $resultSet->rows;
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

        $mutation = $plan->requireMutation();

        $resultSet = $this->resultSelectRunner->runResultSet(
            $plan->sql(),
            fn (string $s) => $this->connection->query($s),
            $this->platform->resultColumnTypeResolver(),
        );
        $impact = $this->applyShadow($mutation, $resultSet, $sql);

        return $impact->affectedRowCount($plan->affectedRowsMode());
    }
}
