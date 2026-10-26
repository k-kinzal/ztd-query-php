<?php

declare(strict_types=1);

namespace Fuzz\Fixture;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Ends the observed connection before catalog restoration can encounter its locks.
 *
 * KILL returns before thread cleanup finishes. An independent administrator must
 * confirm that the captured connection identity has disappeared; a failed read
 * cannot establish disconnection. The bounded wait remains inside the target's
 * ordinary per-input timeout.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/kill.html.
 */
final class Connections
{
    /**
     * Disconnects only the input's connection and requires its removal within five seconds.
     *
     * @param positive-int $connection The identity captured before fixture setup
     * @throws RuntimeException When the connection cannot be terminated or its absence confirmed
     */
    public function release(PDO $administrator, int $connection): void
    {
        try {
            if ($administrator->exec('KILL CONNECTION ' . $connection) === false && ($administrator->errorInfo()[1] ?? null) !== 1094) {
                throw new RuntimeException('Cannot terminate the differential input connection: ' . json_encode($administrator->errorInfo()));
            }
        } catch (PDOException $failure) {
            if (($failure->errorInfo[1] ?? null) !== 1094) {
                throw new RuntimeException('Cannot terminate the differential input connection.', 0, $failure);
            }
        }
        $deadline = hrtime(true) + 5_000_000_000;
        do {
            $remaining = $administrator->query('SELECT ID FROM information_schema.PROCESSLIST WHERE ID = ' . $connection);
            if ($remaining === false) {
                throw new RuntimeException('Cannot confirm the differential input connection was terminated: ' . json_encode($administrator->errorInfo()));
            }
            if ($remaining->fetchAll(PDO::FETCH_COLUMN) === [] && hrtime(true) <= $deadline) {
                return;
            }
            usleep(10000);
        } while (hrtime(true) < $deadline);

        throw new RuntimeException('The differential input connection did not terminate within five seconds.');
    }
}
