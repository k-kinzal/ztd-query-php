<?php

declare(strict_types=1);

namespace Fuzz\Target\Status;

use PDO;

/**
 * Independent status baselines and client-side wire measurements before a listing.
 * All queries are issued synchronously by this process; mysqlnd statistics include PDO.
 * Source: https://www.php.net/manual/en/mysqlnd.stats.php.
 */
final class Snapshot
{
    /**
     * @param array<string, string> $global The first GLOBAL snapshot
     * @param array<string, string> $session The following SESSION snapshot
     * @param string $globalBytes Wire bytes received for both snapshot replies
     * @param string $sessionBytes Wire bytes received for the SESSION reply
     * @param float $instant The wall clock before the first snapshot
     */
    public function __construct(public readonly array $global, public readonly array $session, public readonly string $globalBytes, public readonly string $sessionBytes, public readonly float $instant)
    {
    }

    /**
     * Captures both scopes, including the byte totals before each reply was sent.
     */
    public static function capture(PDO $pdo): ?self
    {
        $instant = microtime(true);
        $first = mysqli_get_client_stats()['bytes_received'] ?? null;
        $global = self::read($pdo, 'SHOW GLOBAL STATUS');
        $second = mysqli_get_client_stats()['bytes_received'] ?? null;
        $session = self::read($pdo, 'SHOW SESSION STATUS');
        $last = mysqli_get_client_stats()['bytes_received'] ?? null;
        if ($global === null || $session === null || !is_string($first) || !is_string($second) || !is_string($last) || !ctype_digit($first) || !ctype_digit($second) || !ctype_digit($last)) {
            return null;
        }

        return new self($global, $session, bcsub($last, $first), bcsub($last, $second), $instant);
    }

    /**
     * @return array<string, string>|null A complete, unique status-name map
     */
    public static function read(PDO $pdo, string $sql): ?array
    {
        $statement = $pdo->query($sql);

        return $statement === false ? null : self::index($statement->fetchAll(PDO::FETCH_NUM));
    }

    /**
     * @param array<mixed> $rows The observed rows
     * @return array<string, string>|null A name map, refusing missing or duplicate entries
     */
    public static function index(array $rows): ?array
    {
        $values = [];
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== 2 || !is_string($row[0] ?? null) || !is_string($row[1] ?? null) || $row[0] === '' || isset($values[$row[0]])) {
                return null;
            }
            $values[$row[0]] = $row[1];
        }
        ksort($values);

        return $values === [] ? null : $values;
    }
}
