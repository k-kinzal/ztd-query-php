<?php

declare(strict_types=1);

namespace Fuzz\Observation\Process;

use Fuzz\Observation\Observer;
use Fuzz\Observation\Record;
use PDO;
use RuntimeException;

/**
 * An independent connection identity, statement-clock age and process-table snapshot.
 *
 * @phpstan-type ProcessRow array{int, string, string, ?string, string, int, ?string, ?string}
 */
final class Sample
{
    /**
     * @param array<int, ProcessRow> $rows The complete process table indexed by unique identity
     */
    public function __construct(public readonly int $current, public readonly int $age, public readonly string $scheduler, public readonly array $rows)
    {
    }

    /**
     * Reads identity and clock independently of the table that brackets SHOW PROCESSLIST.
     *
     * @phpstan-impure
     * @throws RuntimeException When a required sample cannot be read or has an invalid shape
     */
    public static function read(PDO $pdo, ?Record $record = null): self
    {
        $observer = new Observer();
        $clocks = $observer->rows($pdo, 'SELECT CONNECTION_ID(), CAST(UNIX_TIMESTAMP(SYSDATE())-@@timestamp AS SIGNED), @@GLOBAL.event_scheduler', $record);
        $values = count($clocks) === 1 ? $clocks[0] : null;
        if (!is_array($values) || count($values) !== 3 || !is_int($values[0] ?? null) || $values[0] <= 0 || !is_int($values[1] ?? null) || !in_array($values[2] ?? null, ['ON', 'OFF', 'DISABLED'], true)) {
            throw new RuntimeException('Cannot sample the process-list identity, clock and scheduler.');
        }
        $rows = self::indexed($observer->rows($pdo, 'SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO FROM information_schema.PROCESSLIST', $record));
        if ($rows === null || !isset($rows[$values[0]])) {
            throw new RuntimeException('The process-list sample is malformed or does not contain its owner.');
        }

        return new self($values[0], $values[1], $values[2], $rows);
    }

    /**
     * Rejects malformed rows and repeated identities, retaining every row and field.
     *
     * @param array<mixed> $rows
     * @return array<int, ProcessRow>|null
     */
    public static function indexed(array $rows): ?array
    {
        $indexed = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_is_list($row) || count($row) !== 8 || !is_int($row[0]) || $row[0] <= 0 || isset($indexed[$row[0]]) || !is_string($row[1]) || !is_string($row[2]) || (!is_string($row[3]) && $row[3] !== null) || !is_string($row[4]) || !is_int($row[5]) || (!is_string($row[6]) && $row[6] !== null) || (!is_string($row[7]) && $row[7] !== null)) {
                return null;
            }
            $indexed[$row[0]] = [$row[0], $row[1], $row[2], $row[3], $row[4], $row[5], $row[6], $row[7]];
        }
        ksort($indexed);

        return $indexed;
    }
}
