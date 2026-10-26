<?php

declare(strict_types=1);

namespace Fuzz\Execution;

use Fuzz\Observation\Record;
use PDO;
use PDOException;
use RuntimeException;

/**
 * Requires an acknowledged lifecycle command to reach its observable transition.
 * Transport unavailability and shutdown-in-progress differ from other SQL errors. Matching
 * timeouts cannot count as agreement between two database implementations.
 */
final class LifecycleTransition
{
    /**
     * Requires disconnection within ten seconds, then the requested server state.
     * Shutdown shares that deadline; restart has a separate thirty-second budget.
     *
     * @throws RuntimeException When the transition fails or a probe is refused
     */
    public function await(PDO $owner, string $dsn, string $user, string $password, LifecycleAction $action, Record $record): ?PDO
    {
        $record->phase = 'connection termination';
        $record->raw['disconnected'] = false;
        $record->raw['reconnected'] = false;
        $owner->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT);
        $deadline = hrtime(true) + 10_000_000_000;
        do {
            if (!$record->raw['disconnected'] && $owner->exec('DO 0') === false) {
                $error = $owner->errorInfo();
                if (in_array($error[1] ?? null, [2002, 2006, 2013], true)) {
                    $record->raw['disconnected'] = true;
                } elseif ($error[0] !== '08S01' || ($error[1] ?? null) !== 1053) {
                    throw new RuntimeException('The lifecycle connection probe failed without disconnection: ' . json_encode($error));
                }
                /** ER_SERVER_SHUTDOWN precedes socket closure; it does not establish disconnection. */
            }
            if ($record->raw['disconnected'] && hrtime(true) <= $deadline) {
                if ($action === LifecycleAction::Restart) {
                    $record->phase = 'server restart';
                    $again = $this->connect($dsn, $user, $password, true) ?? throw new RuntimeException('The lifecycle server did not restart within thirty seconds.');
                    $record->raw['reconnected'] = true;
                    return $again;
                }
                $record->phase = 'server shutdown';
                if ($this->connect($dsn, $user, $password, false) === null && hrtime(true) <= $deadline) {
                    return null;
                }
            }
            usleep(20000);
        } while (hrtime(true) < $deadline);

        throw new RuntimeException('The lifecycle server did not finish disconnecting and shutting down within ten seconds.');
    }

    /**
     * Attempts a usable connection once, or waits at most thirty seconds for SQL readiness.
     * Shutdown-in-progress is not ready; other SQL and authentication errors fail observation.
     *
     * @throws RuntimeException When a probe fails for a reason other than unavailability or shutdown
     */
    public function connect(string $dsn, string $user, string $password, bool $wait): ?PDO
    {
        $deadline = hrtime(true) + 30_000_000_000;
        do {
            $connection = $this->probe($dsn, $user, $password);
            if ($connection !== null && (!$wait || hrtime(true) <= $deadline)) {
                return $connection;
            }
            if (!$wait) {
                return null;
            }
            usleep(20000);
        } while (hrtime(true) < $deadline);

        return null;
    }

    /**
     * Confirms that an authenticated connection can execute SQL before treating it as available.
     *
     * ER_SERVER_SHUTDOWN (1053/08S01) can arrive before the socket closes.
     * Source: https://dev.mysql.com/doc/mysql-errors/8.4/en/server-error-reference.html#error_er_server_shutdown.
     *
     * @throws RuntimeException When a refusal is neither a transport failure nor shutdown-in-progress
     */
    public function probe(string $dsn, string $user, string $password): ?PDO
    {
        try {
            $connection = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 1]);
            $connection->exec('DO 0');
            return $connection;
        } catch (PDOException $failure) {
            $shuttingDown = ($failure->errorInfo[0] ?? null) === '08S01' && ($failure->errorInfo[1] ?? null) === 1053;
            if (!in_array($failure->errorInfo[1] ?? null, [2002, 2006, 2013], true) && !$shuttingDown) {
                throw new RuntimeException('The lifecycle readiness probe was refused: ' . $failure->getMessage(), 0, $failure);
            }
            return null;
        }
    }
}
