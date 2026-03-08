<?php

declare(strict_types=1);

namespace Spec\Probe;

use PDO;
use PDOException;

/**
 * Probes SQLite witnesses by preparing them against an in-memory database to
 * capture parser and binder failures without execution.
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
     * Runs the witness through `PDO::prepare()` and normalizes SQLite's response.
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
