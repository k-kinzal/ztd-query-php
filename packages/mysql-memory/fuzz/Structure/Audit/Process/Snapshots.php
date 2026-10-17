<?php

declare(strict_types=1);

namespace Fuzz\Structure\Audit\Process;

use Fuzz\Target\Process\Sample;
use mysqli;
use mysqli_result;
use MySqlMemory\Session\Session;
use MySqlMemory\System\Server\Processlist;

/**
 * Brackets a direct listing with independently sampled identities and clocks.
 */
final class Snapshots
{
    /**
     * Reads the native identity and clock independently of its process table.
     */
    public function native(mysqli $connection): ?Sample
    {
        $clock = $this->read($connection, 'SELECT CONNECTION_ID(), CAST(UNIX_TIMESTAMP(SYSDATE())-@@timestamp AS SIGNED), @@GLOBAL.event_scheduler');
        $rows = $this->read($connection, 'SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO FROM information_schema.PROCESSLIST');
        $current = filter_var($clock[0][0] ?? null, FILTER_VALIDATE_INT);
        $age = filter_var($clock[0][1] ?? null, FILTER_VALIDATE_INT);
        $scheduler = $clock[0][2] ?? null;
        $indexed = $rows === null ? null : $this->indexed($rows);

        return $current === false || $age === false || !is_string($scheduler) || $indexed === null ? null : new Sample($current, $age, $scheduler, $indexed);
    }

    /**
     * Uses the instance clock and connection registry, without submitting SQL.
     */
    public function memory(Session $session): ?Sample
    {
        $rows = [];
        foreach ($session->instance->sessions as $id => $reference) {
            $open = $reference->get();
            if ($open !== null && isset($session->instance->registry->threads->connected[$id])) {
                $rows[] = array_slice(array_values(Processlist::row($open, $id === $session->id, 'executing')), 0, 8);
            }
        }
        $daemon = $session->instance->registry->eventScheduler->row();
        if ($daemon !== null) {
            $rows[] = array_slice(array_values($daemon), 0, 8);
        }
        $indexed = Sample::indexed($rows);
        $age = (int) floor($session->instance->registry->threads->now()) - (int) floor((float) $session->variables->read('timestamp'));

        return $indexed === null ? null : new Sample($session->id, $age, (string) $session->variables->read('event_scheduler'), $indexed);
    }

    /**
     * Converts only the two integer fields that mysqli exposes as text.
     *
     * @param array<mixed> $rows
     * @return array<int, array{int, string, string, ?string, string, int, ?string, ?string}>|null
     */
    public function indexed(array $rows): ?array
    {
        $converted = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !array_is_list($row) || count($row) !== 8) {
                return null;
            }
            foreach ([0, 5] as $index) {
                $value = $row[$index];
                if (!is_string($value) || preg_match('/\A-?(?:0|[1-9][0-9]*)\z/', $value) !== 1 || ($number = filter_var($value, FILTER_VALIDATE_INT)) === false) {
                    return null;
                }
                $row[$index] = $number;
            }
            $converted[] = $row;
        }

        return Sample::indexed($converted);
    }

    /**
     * @return list<list<string|null>>|null
     */
    public function read(mysqli $connection, string $sql): ?array
    {
        $result = $connection->query($sql);
        if (!$result instanceof mysqli_result) {
            return null;
        }
        $rows = array_map(static fn (array $row): array => array_map((new \Fuzz\Structure\Audit\Native())->value(...), array_values($row)), $result->fetch_all(MYSQLI_NUM));
        $result->free();

        return $rows;
    }
}
