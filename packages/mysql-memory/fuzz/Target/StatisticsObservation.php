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
            $connected = (string) self::threads($pdo);
            $info = $pdo->getAttribute(PDO::ATTR_SERVER_INFO);
            if (is_string($info) && preg_match('/  Threads: (\d+)  /', $info, $match) === 1 && $match[1] === $connected) {
                return true;
            }
            usleep(10000);
        }

        return false;
    }
    /**
     * Counts process-list threads, including scheduler daemons omitted from Threads_connected.
     */
    public static function threads(PDO $pdo): int
    {
        $statement = $pdo->query('SHOW PROCESSLIST');

        return $statement === false ? 0 : count($statement->fetchAll(PDO::FETCH_NUM));
    }

}
