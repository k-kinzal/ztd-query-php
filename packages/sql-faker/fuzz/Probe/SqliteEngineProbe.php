<?php

declare(strict_types=1);

namespace Fuzz\Probe;

use PDO;
use PDOException;

/**
 * Probes SQLite by preparing generated SQL against an in-memory database.
 *
 * This captures parser and binder errors without executing the statement.
 */
final class SqliteEngineProbe implements EngineProbe
{
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * Returns the SQL dialect handled by this probe.
     */
    public function dialect(): string
    {
        return 'sqlite';
    }

    /**
     * Runs the statement through `PDO::prepare()` and returns a normalized view
     * of SQLite's response.
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
            return ProbeResult::failed(ProbePhase::Prepare, null, null, $e->getMessage());
        }
    }
}
