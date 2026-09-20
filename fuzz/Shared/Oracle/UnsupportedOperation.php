<?php

declare(strict_types=1);

namespace Fuzz\Shared\Oracle;

use Fuzz\Shared\Database\Sandbox;
use Fuzz\Shared\Database\SessionExecutor;
use Fuzz\Shared\Input\Command;
use PDOException;
use ZtdQuery\Connection\Exception\DatabaseException;

/**
 * Check a capability explicitly excluded by the platform specification.
 */
final class UnsupportedOperation
{
    /**
     * PostgreSQL ALTER is explicitly unsupported in docs/postgres-spec.md.
     * Validate native syntax in a rolled-back transaction, then require strict
     * rejection by ZTD without changing either catalog or its rows.
     *
     * @throws Finding
     * @throws PDOException
     */
    public static function verify(Sandbox $native, SessionExecutor $ztd, Command $command): void
    {
        $before = $native->catalog->snapshot();
        $native->pdo->beginTransaction();
        try {
            $native->pdo->exec($command->sql);
        } finally {
            $native->pdo->rollBack();
        }
        $failure = null;
        try {
            $ztd->execute($command);
        } catch (PDOException|DatabaseException $error) {
            $failure = $error;
        }
        Rejection::verify($failure, 'unsupported');
        Comparison::same($before, $native->catalog->snapshot(), 'Native capability probe changed state.');
    }

}
