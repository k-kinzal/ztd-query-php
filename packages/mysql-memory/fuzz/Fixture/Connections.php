<?php

declare(strict_types=1);

namespace Fuzz\Fixture;

use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use PDO;
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
    public function release(PDO $administrator, int $connection, ?Record $record = null): void
    {
        try {
            $result = (new Tables())->observe($administrator, 'KILL CONNECTION ' . $connection, $record);
        } catch (RuntimeException $failure) {
            throw new RuntimeException('Cannot terminate the differential input connection.', 0, $failure);
        }
        $error = $result['error'] ?? null;
        if ($error !== null && (!is_array($error) || ($error[0] ?? null) !== 1094)) {
            throw new RuntimeException('Cannot terminate the differential input connection: ' . json_encode($error));
        }
        $deadline = hrtime(true) + 5_000_000_000;
        do {
            $remaining = (new Observer())->rows($administrator, 'SELECT ID FROM information_schema.PROCESSLIST WHERE ID = ' . $connection, $record);
            if ($remaining === [] && hrtime(true) <= $deadline) {
                return;
            }
            foreach ($remaining as $row) {
                if (!is_array($row) || count($row) !== 1 || ($row[0] ?? null) !== $connection) {
                    throw new RuntimeException('The connection-termination probe returned an invalid identity.');
                }
            }
            usleep(10000);
        } while (hrtime(true) < $deadline);

        throw new RuntimeException('The differential input connection did not terminate within five seconds.');
    }
}
