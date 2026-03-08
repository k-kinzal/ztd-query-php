<?php

declare(strict_types=1);

namespace Fuzz\Probe;

use PDO;
use PDOException;

/**
 * Probes MySQL by preparing generated SQL without executing it.
 *
 * The probe also shortens lock-related timeouts so blocking statements fail
 * quickly instead of stalling the fuzz loop.
 */
final class MySqlEngineProbe implements EngineProbe
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
        $this->pdo->exec('SET SESSION lock_wait_timeout = 1');
        $this->pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
    }

    /**
     * Returns the SQL dialect handled by this probe.
     */
    public function dialect(): string
    {
        return 'mysql';
    }

    /**
     * Runs the statement through `PDO::prepare()` and returns a normalized view
     * of MySQL's acceptance or rejection.
     */
    public function observe(string $sql): ProbeResult
    {
        if ($sql === '') {
            return ProbeResult::accepted();
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            if ($stmt === false) {
                return ProbeResult::failed(ProbePhase::Prepare, null, null, 'PDO::prepare returned false');
            }

            return ProbeResult::accepted(ProbePhase::Prepare);
        } catch (PDOException $e) {
            $sqlState = is_string($e->errorInfo[0] ?? null) ? $e->errorInfo[0] : null;
            $errorCode = is_numeric($e->errorInfo[1] ?? null) ? (int) $e->errorInfo[1] : null;

            return ProbeResult::failed(ProbePhase::Prepare, $sqlState, $errorCode, $e->getMessage());
        }
    }
}
