<?php

declare(strict_types=1);

namespace Fuzz\Target;

use PDO;

/**
 * Waits for recently closed MySQL connection threads to leave both statistics views.
 */
final class StatisticsObservation
{
    /**
     * Establishes a quiet fixture before measuring exact counter changes.
     */
    public static function settled(PDO $pdo): bool
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $statement = $pdo->query("SHOW GLOBAL STATUS LIKE 'Threads_connected'");
            $connected = $statement === false ? false : $statement->fetchColumn(1);
            $info = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
            if (is_string($info) && preg_match('/  Threads: (\d+)  /', $info, $match) === 1 && $match[1] === $connected) {
                return true;
            }
            usleep(10000);
        }

        return false;
    }
}
