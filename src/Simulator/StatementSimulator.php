<?php

declare(strict_types=1);

namespace ZtdQuery\Simulator;

use ZtdQuery\Config\UnknownSchemaBehavior;
use ZtdQuery\Config\UnsupportedSqlBehavior;
use ZtdQuery\Config\ZtdConfig;
use ZtdQuery\Exception\UnknownSchemaException;
use ZtdQuery\Exception\UnsupportedSqlException;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\ZteSession;
use RuntimeException;

/**
 * Executes rewritten statements and applies shadow mutations for exec().
 */
final class StatementSimulator
{
    /**
     * Session context for rewrite and mutation application.
     *
     * @var ZteSession
     */
    private ZteSession $session;

    /**
     * ZTD configuration for handling unsupported SQL.
     *
     * @var ZtdConfig
     */
    private ZtdConfig $config;

    /**
     * @param ZteSession $session Current ZTD session.
     * @param ZtdConfig $config ZTD configuration.
     */
    public function __construct(ZteSession $session, ZtdConfig $config)
    {
        $this->session = $session;
        $this->config = $config;
    }

    /**
     * Simulate exec() by running result-select and updating shadow state.
     *
     * @param callable(string): (\PDOStatement|false) $executor
     */
    public function simulate(string $statement, callable $executor): int|false
    {
        $plan = $this->session->rewrite($statement);

        if ($plan->kind() === QueryKind::FORBIDDEN) {
            return $this->handleUnsupportedSql($statement);
        }

        if ($plan->kind() === QueryKind::UNKNOWN_SCHEMA) {
            return $this->handleUnknownSchema($statement, $plan->unknownIdentifier());
        }

        if ($plan->kind() === QueryKind::READ) {
            try {
                $stmt = $executor($plan->sql());
            } catch (\PDOException $e) {
                if ($this->isUnknownSchemaError($e)) {
                    return $this->handleUnknownSchema($statement, $this->extractIdentifierFromError($e));
                }
                throw $e;
            }
            if ($stmt === false) {
                return false;
            }
            return $stmt->rowCount();
        }

        $mutation = $plan->mutation();
        if ($mutation === null) {
            throw new RuntimeException('ZTD Write Protection: Missing shadow mutation for write simulation.');
        }

        try {
            $rows = $this->session->resultSelectRunner()->run($plan->sql(), $executor);
        } catch (\PDOException $e) {
            if ($this->isUnknownSchemaError($e)) {
                return $this->handleUnknownSchema($statement, $this->extractIdentifierFromError($e));
            }
            throw $e;
        }
        $this->session->shadowApplier()->apply($mutation, $rows);

        return count($rows);
    }

    /**
     * Handle unsupported SQL based on configuration.
     *
     * @return int Returns 0 for ignore/notice modes, throws for exception mode.
     * @throws UnsupportedSqlException When exception mode is active.
     */
    private function handleUnsupportedSql(string $statement): int
    {
        $behavior = $this->config->resolveUnsupportedBehavior($statement);

        switch ($behavior) {
            case UnsupportedSqlBehavior::Ignore:
                return 0;

            case UnsupportedSqlBehavior::Notice:
                trigger_error(
                    sprintf('[ZTD Notice] Unsupported SQL ignored: %s', $statement),
                    E_USER_NOTICE
                );

                return 0;

            case UnsupportedSqlBehavior::Exception:
            default:
                throw new UnsupportedSqlException($statement, 'Unsupported');
        }
    }

    /**
     * Handle unknown schema based on configuration.
     *
     * @return int Returns 0 for passthrough/empty/notice modes, throws for exception mode.
     * @throws UnknownSchemaException When exception mode is active.
     */
    private function handleUnknownSchema(string $statement, ?string $identifier): int
    {
        $behavior = $this->config->unknownSchemaBehavior();
        $identifier = $identifier ?? 'unknown';

        switch ($behavior) {
            case UnknownSchemaBehavior::Passthrough:
                // For passthrough, we should execute the original query
                // But since we're in simulate(), returning 0 is acceptable
                // The actual passthrough behavior is better handled at higher level
                return 0;

            case UnknownSchemaBehavior::EmptyResult:
                return 0;

            case UnknownSchemaBehavior::Notice:
                trigger_error(
                    sprintf('[ZTD Notice] Unknown table referenced: %s', $identifier),
                    E_USER_NOTICE
                );
                return 0;

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
    private function isUnknownSchemaError(\PDOException $e): bool
    {
        $errorCode = $e->errorInfo[1] ?? 0;
        return $errorCode === 1054 || $errorCode === 1146 || $errorCode === 1327;
    }

    /**
     * Extract the identifier name from a PDOException message.
     */
    private function extractIdentifierFromError(\PDOException $e): string
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
