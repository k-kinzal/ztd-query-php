<?php

declare(strict_types=1);

namespace Fuzz\Observation\Status;

use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use Fuzz\Observation\Traffic;
use PDO;
use RuntimeException;

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
     * @param Traffic $globalTraffic Transport measurements around the GLOBAL snapshot
     * @param Traffic $sessionTraffic Transport measurements around the SESSION snapshot
     * @param float $instant The wall clock before the first snapshot
     */
    public function __construct(public readonly array $global, public readonly array $session, public readonly Traffic $globalTraffic, public readonly Traffic $sessionTraffic, public readonly float $instant)
    {
    }

    /**
     * Captures both scopes, including the byte totals before each reply was sent.
     *
     * @throws RuntimeException When the client statistics or a required snapshot are unavailable
     */
    public static function capture(PDO $pdo, ?Record $record = null): self
    {
        $instant = microtime(true);
        $globalTraffic = new Traffic();
        $sessionTraffic = new Traffic();
        $global = self::read($pdo, 'SHOW GLOBAL STATUS', $record, $globalTraffic);
        $session = self::read($pdo, 'SHOW SESSION STATUS', $record, $sessionTraffic);

        return new self($global, $session, $globalTraffic, $sessionTraffic, $instant);
    }

    /**
     * @return array<string, string> A complete, unique status-name map
     * @throws RuntimeException When the required status inventory is unreadable or malformed
     */
    public static function read(PDO $pdo, string $sql, ?Record $record = null, ?Traffic $traffic = null): array
    {
        $values = self::index((new Observer())->rows($pdo, $sql, $record, $traffic));
        if ($values === null) {
            throw new RuntimeException('The required status inventory is empty or malformed.');
        }
        return $values;
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
