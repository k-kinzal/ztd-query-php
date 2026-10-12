<?php

declare(strict_types=1);

namespace Fuzz\Target\Process;

use PDO;
use PDOException;

/**
 * Observes completed connection removal after KILL acknowledges its request.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/kill.html.
 */
final class Disconnection
{
    /**
     * Waits for a killed connection to leave the process list. KILL QUERY leaves
     * the connection alive and needs no disconnection barrier. Query failures
     * cannot establish that the target has disappeared.
     */
    public function wait(PDO $observer, int $id, bool $required, float $timeout = 1.0): bool
    {
        if (!$required) {
            return true;
        }
        $deadline = hrtime(true) / 1e9 + max(0.0, $timeout);
        do {
            try {
                $statement = $observer->query('SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID=' . $id);
                if ($statement === false) {
                    return false;
                }
                $present = $statement->fetchColumn();
                $statement->closeCursor();
            } catch (PDOException) {
                return false;
            }
            if ($present === 0 || $present === '0') {
                return true;
            }
            if (hrtime(true) / 1e9 >= $deadline) {
                return false;
            }
            usleep(10000);
        } while (true);
    }
}
