<?php

declare(strict_types=1);

namespace Fuzz\Target\Process;

use PDO;

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
     */
    public static function read(PDO $pdo): ?self
    {
        $clock = $pdo->query('SELECT CONNECTION_ID(), CAST(UNIX_TIMESTAMP(SYSDATE())-@@timestamp AS SIGNED), @@GLOBAL.event_scheduler');
        $values = $clock === false ? false : $clock->fetch(PDO::FETCH_NUM);
        $statement = $pdo->query('SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO FROM information_schema.PROCESSLIST');
        $rows = $statement === false ? null : self::indexed($statement->fetchAll(PDO::FETCH_NUM));
        if (!is_array($values) || !is_int($values[0] ?? null) || !is_int($values[1] ?? null) || !is_string($values[2] ?? null) || $rows === null) {
            return null;
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
