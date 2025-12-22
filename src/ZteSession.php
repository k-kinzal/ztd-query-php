<?php

declare(strict_types=1);

namespace ZtdQuery;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use ZtdQuery\Rewrite\SqlRewriter;
use ZtdQuery\Schema\SchemaRegistry;
use ZtdQuery\Shadow\ShadowApplier;
use ZtdQuery\Shadow\ShadowStore;

/**
 * Aggregates ZTD session state and core collaborators.
 */
final class ZteSession
{
    /**
     * Shadow store containing simulated rows.
     *
     * @var ShadowStore
     */
    private ShadowStore $shadowStore;

    /**
     * Schema registry for column and primary key lookup.
     *
     * @var SchemaRegistry
     */
    private SchemaRegistry $schemaRegistry;

    /**
     * Guard used to classify and block unsafe statements.
     *
     * @var QueryGuard
     */
    private QueryGuard $guard;

    /**
     * SQL rewrite pipeline implementation.
     *
     * @var SqlRewriter
     */
    private SqlRewriter $rewriter;

    /**
     * Applies mutations to the shadow store.
     *
     * @var ShadowApplier
     */
    private ShadowApplier $shadowApplier;

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
     * Whether ZTD mode is enabled for this session.
     *
     * @var bool
     */
    private bool $enabled = false;

    /**
     * @param ShadowStore $shadowStore Shadow state used for CTE overlay.
     * @param SchemaRegistry $schemaRegistry Schema cache for column/PK lookups.
     * @param QueryGuard $guard Write-protection guard.
     * @param SqlRewriter $rewriter Rewrite pipeline for SQL.
     * @param ShadowApplier $shadowApplier Applies mutations to shadow state.
     * @param ResultSelectRunner $resultSelectRunner Executes result-select queries.
     * @param ZtdConfig $config ZTD configuration for error handling.
     */
    public function __construct(
        ShadowStore $shadowStore,
        SchemaRegistry $schemaRegistry,
        QueryGuard $guard,
        SqlRewriter $rewriter,
        ShadowApplier $shadowApplier,
        ResultSelectRunner $resultSelectRunner,
        ZtdConfig $config
    ) {
        $this->shadowStore = $shadowStore;
        $this->schemaRegistry = $schemaRegistry;
        $this->guard = $guard;
        $this->rewriter = $rewriter;
        $this->shadowApplier = $shadowApplier;
        $this->resultSelectRunner = $resultSelectRunner;
        $this->config = $config;
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
     * Expose the shadow store for inspection and seeding.
     */
    public function shadowStore(): ShadowStore
    {
        return $this->shadowStore;
    }

    /**
     * Expose the schema registry used by the session.
     */
    public function schemaRegistry(): SchemaRegistry
    {
        return $this->schemaRegistry;
    }

    /**
     * Expose the query guard.
     */
    public function guard(): QueryGuard
    {
        return $this->guard;
    }

    /**
     * Expose the SQL rewriter.
     */
    public function rewriter(): SqlRewriter
    {
        return $this->rewriter;
    }

    /**
     * Expose the shadow applier.
     */
    public function shadowApplier(): ShadowApplier
    {
        return $this->shadowApplier;
    }

    /**
     * Expose the result-select runner.
     */
    public function resultSelectRunner(): ResultSelectRunner
    {
        return $this->resultSelectRunner;
    }

    /**
     * Rewrite SQL using the configured rewriter.
     */
    public function rewrite(string $sql): RewritePlan
    {
        return $this->rewriter->rewrite($sql);
    }

    /**
     * Execute a statement with ZTD rewriting and shadow application.
     *
     * @param string $sql The original SQL statement.
     * @param array<int|string, mixed>|null $params Bound parameters for execution.
     * @param callable(string, array<int|string, mixed>|null): (PDOStatement|false) $executor Callback to execute the rewritten SQL.
     * @return array{
     *   success: bool,
     *   kind: QueryKind,
     *   rows: array<int, array<string, mixed>>,
     *   passthrough: bool,
     *   rewrittenStatement: PDOStatement|null
     * }
     */
    public function executeStatement(
        string $sql,
        ?array $params,
        callable $executor
    ): array {
        $plan = $this->rewrite($sql);

        if ($plan->kind() === QueryKind::FORBIDDEN) {
            $result = $this->handleForbidden($sql);
            return [
                'success' => $result['success'],
                'kind' => QueryKind::FORBIDDEN,
                'rows' => [],
                'passthrough' => $result['passthrough'],
                'rewrittenStatement' => null,
            ];
        }

        if ($plan->kind() === QueryKind::UNKNOWN_SCHEMA) {
            $result = $this->handleUnknownSchema($sql, $plan->unknownIdentifier());
            return [
                'success' => $result['success'],
                'kind' => QueryKind::UNKNOWN_SCHEMA,
                'rows' => $result['rows'] ?? [],
                'passthrough' => $result['passthrough'],
                'rewrittenStatement' => null,
            ];
        }

        try {
            $rewrittenStatement = $executor($plan->sql(), $params);
        } catch (PDOException $e) {
            if ($this->isUnknownSchemaError($e)) {
                $result = $this->handleUnknownSchema($sql, $this->extractIdentifierFromError($e));
                return [
                    'success' => $result['success'],
                    'kind' => QueryKind::UNKNOWN_SCHEMA,
                    'rows' => $result['rows'] ?? [],
                    'passthrough' => $result['passthrough'],
                    'rewrittenStatement' => null,
                ];
            }
            throw $e;
        }

        if ($rewrittenStatement === false) {
            return [
                'success' => false,
                'kind' => $plan->kind(),
                'rows' => [],
                'passthrough' => false,
                'rewrittenStatement' => null,
            ];
        }

        if ($plan->kind() === QueryKind::READ) {
            return [
                'success' => true,
                'kind' => QueryKind::READ,
                'rows' => [],
                'passthrough' => false,
                'rewrittenStatement' => $rewrittenStatement,
            ];
        }

        // WRITE query: fetch results and apply to shadow store
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $rewrittenStatement->fetchAll(PDO::FETCH_ASSOC);

        $mutation = $plan->mutation();
        if ($mutation === null) {
            throw new RuntimeException('ZTD Write Protection: Missing shadow mutation for write simulation.');
        }
        $this->shadowApplier->apply($mutation, $rows);

        return [
            'success' => true,
            'kind' => QueryKind::WRITE_SIMULATED,
            'rows' => $rows,
            'passthrough' => false,
            'rewrittenStatement' => $rewrittenStatement,
        ];
    }

    /**
     * Execute an exec-style statement with ZTD rewriting and shadow application.
     *
     * @param string $sql The original SQL statement.
     * @param callable(string): (int|false) $directExecutor Callback to execute SQL directly (for non-ZTD passthrough).
     * @param callable(string): (PDOStatement|false) $queryExecutor Callback to execute SQL and get PDOStatement.
     * @return int|false The number of affected rows, or false on failure.
     */
    public function execStatement(
        string $sql,
        callable $directExecutor,
        callable $queryExecutor
    ): int|false {
        $plan = $this->rewrite($sql);

        if ($plan->kind() === QueryKind::FORBIDDEN) {
            $this->handleForbidden($sql);
            // handleForbidden throws exception or returns array with success=false
            // For exec(), return 0 for ignore/notice modes
            return 0;
        }

        if ($plan->kind() === QueryKind::UNKNOWN_SCHEMA) {
            $result = $this->handleUnknownSchema($sql, $plan->unknownIdentifier());
            if ($result['passthrough']) {
                return $directExecutor($sql);
            }
            return $result['rowCount'] ?? 0;
        }

        if ($plan->kind() === QueryKind::READ) {
            try {
                $stmt = $queryExecutor($plan->sql());
            } catch (PDOException $e) {
                if ($this->isUnknownSchemaError($e)) {
                    $result = $this->handleUnknownSchema($sql, $this->extractIdentifierFromError($e));
                    if ($result['passthrough']) {
                        return $directExecutor($sql);
                    }
                    return $result['rowCount'] ?? 0;
                }
                throw $e;
            }
            if ($stmt === false) {
                return false;
            }
            return $stmt->rowCount();
        }

        // WRITE query
        $mutation = $plan->mutation();
        if ($mutation === null) {
            throw new RuntimeException('ZTD Write Protection: Missing shadow mutation for write simulation.');
        }

        try {
            $rows = $this->resultSelectRunner->run($plan->sql(), $queryExecutor);
        } catch (PDOException $e) {
            if ($this->isUnknownSchemaError($e)) {
                $result = $this->handleUnknownSchema($sql, $this->extractIdentifierFromError($e));
                if ($result['passthrough']) {
                    return $directExecutor($sql);
                }
                return $result['rowCount'] ?? 0;
            }
            throw $e;
        }

        $this->shadowApplier->apply($mutation, $rows);

        return count($rows);
    }

    /**
     * Handle unsupported SQL based on configuration.
     *
     * @return array{success: false, passthrough: false}|never
     * @throws UnsupportedSqlException When exception mode is active.
     */
    public function handleForbidden(string $statement): array
    {
        $behavior = $this->config->resolveUnsupportedBehavior($statement);

        switch ($behavior) {
            case UnsupportedSqlBehavior::Ignore:
                return ['success' => false, 'passthrough' => false];

            case UnsupportedSqlBehavior::Notice:
                trigger_error(
                    sprintf('[ZTD Notice] Unsupported SQL ignored: %s', $statement),
                    E_USER_NOTICE
                );

                return ['success' => false, 'passthrough' => false];

            case UnsupportedSqlBehavior::Exception:
            default:
                throw new UnsupportedSqlException($statement, 'Unsupported');
        }
    }

    /**
     * Handle unknown schema based on configuration.
     *
     * @return array{success: bool, passthrough: bool, rows?: array<int, array<string, mixed>>, rowCount?: int}|never
     * @throws UnknownSchemaException When exception mode is active.
     */
    public function handleUnknownSchema(string $statement, ?string $identifier): array
    {
        $behavior = $this->config->unknownSchemaBehavior();
        $identifier = $identifier ?? 'unknown';

        switch ($behavior) {
            case UnknownSchemaBehavior::Passthrough:
                return ['success' => true, 'passthrough' => true];

            case UnknownSchemaBehavior::EmptyResult:
                return ['success' => true, 'passthrough' => false, 'rows' => [], 'rowCount' => 0];

            case UnknownSchemaBehavior::Notice:
                trigger_error(
                    sprintf('[ZTD Notice] Unknown table referenced: %s', $identifier),
                    E_USER_NOTICE
                );
                return ['success' => true, 'passthrough' => false, 'rows' => [], 'rowCount' => 0];

            case UnknownSchemaBehavior::Exception:
            default:
                throw new UnknownSchemaException($statement, $identifier, 'table');
        }
    }

    /**
     * Check if a PDOException is an unknown column/table/variable error.
     *
     * MySQL error codes:
     * - 1054: Unknown column
     * - 1146: Table doesn't exist
     * - 1327: Undeclared variable
     */
    public function isUnknownSchemaError(PDOException $e): bool
    {
        $errorCode = $e->errorInfo[1] ?? 0;
        return $errorCode === 1054 || $errorCode === 1146 || $errorCode === 1327;
    }

    /**
     * Extract the identifier name from a PDOException message.
     */
    public function extractIdentifierFromError(PDOException $e): string
    {
        $message = $e->getMessage();

        // Match "Unknown column 'xxx'" or "Table 'xxx' doesn't exist" or "Undeclared variable: xxx"
        if (preg_match("/Unknown column '([^']+)'/", $message, $matches)) {
            return $matches[1];
        }
        if (preg_match("/Table '[^.]*\.([^']+)' doesn't exist/", $message, $matches)) {
            return $matches[1];
        }
        if (preg_match("/Undeclared variable: ([a-zA-Z0-9_]+)/", $message, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }
}
