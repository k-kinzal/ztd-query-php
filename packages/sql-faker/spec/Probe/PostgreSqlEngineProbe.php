<?php

declare(strict_types=1);

namespace Spec\Probe;

use PDO;
use PDOException;

/**
 * Probes PostgreSQL witnesses inside a savepoint-backed transaction so parse
 * and execution failures are observable without persisting side effects.
 */
final class PostgreSqlEngineProbe implements EngineProbe
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->pdo->exec('BEGIN');
        $this->pdo->exec("SET SESSION statement_timeout = '500ms'");
        $this->pdo->exec("SET SESSION lock_timeout = '500ms'");
    }

    /**
     * Returns the SQL dialect handled by this probe.
     */
    public function dialect(): string
    {
        return 'postgresql';
    }

    /**
     * Executes the witness SQL and normalizes PostgreSQL's response for policy classification.
     */
    public function observe(string $sql): ProbeResult
    {
        if ($sql === '') {
            return ProbeResult::accepted();
        }

        try {
            $this->pdo->exec('SAVEPOINT probe_check');
            $this->pdo->exec($sql);
            $this->pdo->exec('RELEASE SAVEPOINT probe_check');

            return ProbeResult::accepted(ProbePhase::Execute);
        } catch (PDOException $e) {
            $this->resetSavepoint();

            $sqlState = is_string($e->errorInfo[0] ?? null) ? $e->errorInfo[0] : null;
            $errorCode = is_numeric($e->errorInfo[1] ?? null) ? (int) $e->errorInfo[1] : null;

            return ProbeResult::failed(ProbePhase::Execute, $sqlState, $errorCode, $e->getMessage());
        }
    }

    private function resetSavepoint(): void
    {
        try {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT probe_check');
        } catch (PDOException) {
            try {
                $this->pdo->exec('ROLLBACK');
            } catch (PDOException) {
            }
            $this->pdo->exec('BEGIN');
        }
    }
}
