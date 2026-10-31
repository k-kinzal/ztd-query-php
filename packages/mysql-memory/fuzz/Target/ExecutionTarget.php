<?php

declare(strict_types=1);

namespace Fuzz\Target;

use Error;
use mysqli;
use MySqlMemory\Server\MemoryDatabase;

/**
 * Executes each generated SQL input once on each database and compares its replies.
 *
 * Connections retain the same input history, so a generated write can be read by
 * later inputs. A failure includes that history for reproduction. No catalog
 * inspection, state restoration or second native execution changes the result.
 * Bare SHOW STATUS preserves every row and field. Only values observed to differ between
 * two fresh native servers given the same history are checked against their content
 * domains instead of literal equality; every other value and all reply metadata compare exactly.
 */
final class ExecutionTarget
{
    /**
     * Counters with an unsigned 64-bit text domain that differed between two fresh native servers of one
     * release started one after the other and given the same statement history: physical InnoDB
     * activity, process clocks and thread reuse (observed on 5.7.44, 8.0.44 and 8.4.7 images, most
     * recently on two 8.4.7 servers with and without a write history; the logs are kept under
     * build/native-twins). Bytes_sent follows the server's RSA key length.
     * A counter outside this list compares exactly, whatever the emulator can or cannot track.
     */
    private const ENVIRONMENT_COUNTERS = 'Bytes_sent Error_log_latest_write Innodb_buffer_pool_bytes_data Innodb_buffer_pool_bytes_dirty '
        . 'Innodb_buffer_pool_pages_data Innodb_buffer_pool_pages_dirty Innodb_buffer_pool_pages_flushed '
        . 'Innodb_buffer_pool_pages_free Innodb_buffer_pool_read_requests Innodb_buffer_pool_reads '
        . 'Innodb_buffer_pool_write_requests Innodb_data_fsyncs Innodb_data_pending_writes Innodb_data_read '
        . 'Innodb_data_reads Innodb_data_writes Innodb_data_written Innodb_dblwr_pages_written Innodb_dblwr_writes '
        . 'Innodb_log_write_requests Innodb_log_writes Innodb_num_open_files Innodb_os_log_fsyncs Innodb_os_log_written '
        . 'Innodb_pages_created Innodb_pages_read Innodb_pages_written Innodb_redo_log_checkpoint_lsn Innodb_redo_log_current_lsn '
        . 'Innodb_redo_log_flushed_to_disk_lsn Innodb_redo_log_logical_size Innodb_redo_log_uuid Innodb_system_rows_read '
        . 'Max_used_connections Threads_cached Threads_created Uptime Uptime_since_flush_status';

    /**
     * Commands executed by the native image's initialization and readiness connections.
     * Their global totals include those connections; session totals remain exact.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/show-status.html.
     */
    private const STARTUP_COUNTERS = 'Com_change_db Com_create_db Com_create_table Com_flush Com_select Com_set_option Questions';

    /** @var list<string> */
    private array $history = [];

    /**
     * Counts generated inputs started by the entrypoint.
     */
    public int $attempts = 0;

    /**
     * Counts inputs whose replies matched.
     */
    public int $verified = 0;

    /**
     * Shares the two connections and the MemoryDB internal-failure channel.
     * Both connections use MYSQLI_REPORT_OFF so SQL failures remain reply values.
     */
    public function __construct(private readonly mysqli $native, private readonly mysqli $memory, private readonly MemoryDatabase $server)
    {
    }

    /**
     * Requires matching result sets, metadata, affected rows, errors and warning counts.
     *
     * @return bool Whether both executions completed without a SQL error
     * @throws Error When a reply differs or the driver cannot represent it
     */
    public function verify(string $sql, string $input): bool
    {
        $this->history[] = $sql;
        $expected = $this->execute($this->native, $sql);
        $actual = $this->execute($this->memory, $sql);
        $this->server->assertNoInternalFailure();
        $matching = $expected === $actual;
        if ($expected['error'] === null && $actual['error'] === null && count($expected['results']) === 1 && count($actual['results']) === 1) {
            if (preg_match('/\ASHOW\s+(?:(?:SESSION|LOCAL|GLOBAL)\s+)?STATUS\s*;?\s*\z/i', trim($sql)) === 1) {
                $matching = self::status($expected['results'][0], $actual['results'][0], preg_match('/\ASHOW\s+GLOBAL\s+/i', trim($sql)) === 1);
            } elseif (preg_match('/\ASHOW\s+ENGINE\s+(?:ALL|INNODB|PERFORMANCE_SCHEMA|`INNODB`|`PERFORMANCE_SCHEMA`)\s+STATUS\s*;?\s*\z/i', trim($sql)) === 1) {
                $matching = self::engines($expected['results'][0], $actual['results'][0]);
            } elseif (preg_match('/\ASHOW\s+(?:(?:SESSION|LOCAL|GLOBAL)\s+)?VARIABLES\s*;?\s*\z/i', trim($sql)) === 1) {
                $matching = self::variables($expected['results'][0], $actual['results'][0]);
            } elseif (preg_match('/\ASHOW\s+ENGINE\s+(?:ALL|INNODB|`INNODB`)\s+MUTEX\s*;?\s*\z/i', trim($sql)) === 1) {
                $matching = self::latches($expected['results'][0], $actual['results'][0]);
            }
        }
        if (!$matching) {
            throw new Error("MySQL execution differs.\nInput (hex): " . bin2hex($input)
                . "\nSQL: {$sql}\nExpected: " . var_export($expected, true)
                . "\nActual: " . var_export($actual, true)
                . "\nSQL history: " . var_export($this->history, true));
        }
        $this->verified++;
        return $expected['error'] === null;
    }

    /**
     * Retains partial replies when an error occurs while advancing result sets.
     * Driver errors are SQL outcomes; a MemoryDB implementation failure is checked
     * through the server's independent failure channel after both executions.
     *
     * No auxiliary SQL is executed to collect diagnostics: every value below
     * comes from the reply to the generated input itself.
     *
     * @throws Error When the driver returns an invalid reply structure
     * @return array{results: list<array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null}>, error: array{string, int, string}|null}
     */
    public function execute(mysqli $connection, string $sql): array
    {
        $results = [];
        if (!$connection->multi_query($sql)) {
            return ['results' => [], 'error' => [$connection->sqlstate, $connection->errno, $connection->error]];
        }
        do {
            $result = $connection->store_result();
            if ($result === false && $connection->errno !== 0) {
                return ['results' => $results, 'error' => [$connection->sqlstate, $connection->errno, $connection->error]];
            }
            $columns = [];
            $rows = [];
            if ($result !== false) {
                foreach ($result->fetch_fields() as $column) {
                    $metadata = [];
                    foreach (get_object_vars($column) as $key => $value) {
                        if (!is_string($key)) {
                            throw new Error('mysqli column metadata uses named fields.');
                        }
                        $metadata[$key] = $value;
                    }
                    $columns[] = $metadata;
                }
                foreach ($result->fetch_all(MYSQLI_NUM) as $row) {
                    if (!array_is_list($row)) {
                        throw new Error('mysqli numeric rows preserve column order.');
                    }
                    $rows[] = $row;
                }
            }
            $results[] = [
                'columns' => $columns,
                'rows' => $rows,
                'affected' => $connection->affected_rows,
                'insertId' => $connection->insert_id,
                'warnings' => $connection->warning_count,
                'info' => $connection->info,
            ];
            if ($result !== false) {
                $result->free();
            }
            if (!$connection->more_results()) {
                break;
            }
            if (!$connection->next_result()) {
                return ['results' => $results, 'error' => [$connection->sqlstate, $connection->errno, $connection->error]];
            }
        } while (true);
        return ['results' => $results, 'error' => null];
    }

    /**
     * Compares all status rows in order, keeping every variable name and reply field.
     * Only the explicit environment domains below permit unequal values. SQL
     * session command counts and unknown variables have no exception.
     *
     * @param array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null} $expected
     * @param array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null} $actual
     */
    public static function status(array $expected, array $actual, bool $global = false): bool
    {
        foreach (['columns', 'affected', 'insertId', 'warnings', 'info'] as $field) {
            if ($expected[$field] !== $actual[$field]) {
                return false;
            }
        }
        if (count($expected['rows']) !== count($actual['rows'])) {
            return false;
        }
        foreach ($expected['rows'] as $index => $row) {
            $other = $actual['rows'][$index];
            if (count($row) !== 2 || count($other) !== 2 || !is_string($row[0]) || $row[0] !== $other[0] || !is_string($row[1]) || !is_string($other[1])) {
                return false;
            }
            if ($global && in_array($row[0], explode(' ', self::STARTUP_COUNTERS), true)) {
                if (!self::unsigned($row[1]) || !self::unsigned($other[1])) {
                    return false;
                }
                continue;
            }
            $valid = self::environment($row[0], $row[1]);
            if ($valid === null ? $row[1] !== $other[1] : (!$valid || self::environment($row[0], $other[1]) !== true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Requires every physical-latch record of both replies to have the server's form, with equal
     * metadata and completion fields. The set of latches with OS waits belongs to each process:
     * two fresh 8.4.7 servers started one after the other reported 0 and 2 records for the same
     * history (build/native-twins), so the record count and content are not compared.
     *
     * @param array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null} $expected
     * @param array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null} $actual
     */
    public static function latches(array $expected, array $actual): bool
    {
        foreach (['columns', 'affected', 'insertId', 'warnings', 'info'] as $field) {
            if ($expected[$field] !== $actual[$field]) {
                return false;
            }
        }
        foreach ([...$expected['rows'], ...$actual['rows']] as $row) {
            if (count($row) !== 3 || $row[0] !== 'InnoDB' || !is_string($row[1]) || !is_string($row[2])
                || preg_match('/\A(?:rwlock|mutex): [A-Za-z0-9_\/.]+(?::[0-9]+)?\z/', $row[1]) !== 1 || preg_match('/\A(?:waits|spins|os_waits|count)=[0-9]+\z/', $row[2]) !== 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * Compares all variable rows in order, keeping every variable name and reply field.
     * Only the variables of the server instance itself permit unequal values: its host name and
     * the file names derived from it, and its UUID, which two fresh 8.4.7 servers started one
     * after the other reported differently for the same session history (build/native-twins);
     * and the identifiers its connections and statements have reached, which include the image's
     * readiness connections like the startup counters of SHOW STATUS.
     *
     * @param array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null} $expected
     * @param array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null} $actual
     */
    public static function variables(array $expected, array $actual): bool
    {
        foreach (['columns', 'affected', 'insertId', 'warnings', 'info'] as $field) {
            if ($expected[$field] !== $actual[$field]) {
                return false;
            }
        }
        if (count($expected['rows']) !== count($actual['rows'])) {
            return false;
        }
        foreach ($expected['rows'] as $index => $row) {
            $other = $actual['rows'][$index];
            if (count($row) !== 2 || count($other) !== 2 || !is_string($row[0]) || $row[0] !== $other[0] || !is_string($row[1]) || !is_string($other[1])) {
                return false;
            }
            $valid = self::instance($row[0], $row[1]);
            if ($valid === null ? $row[1] !== $other[1] : (!$valid || self::instance($row[0], $other[1]) !== true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validates a variable of the server instance against its content domain; every other
     * name returns null and compares exactly.
     */
    public static function instance(string $name, string $value): ?bool
    {
        $host = '[A-Za-z0-9._-]{1,255}';
        $pattern = match ($name) {
            'hostname' => '/\A' . $host . '\z/',
            'general_log_file' => '/\A\/var\/lib\/mysql\/' . $host . '\.log\z/',
            'slow_query_log_file' => '/\A\/var\/lib\/mysql\/' . $host . '-slow\.log\z/',
            'relay_log' => '/\A' . $host . '-relay-bin\z/',
            'relay_log_basename' => '/\A\/var\/lib\/mysql\/' . $host . '-relay-bin\z/',
            'relay_log_index' => '/\A\/var\/lib\/mysql\/' . $host . '-relay-bin\.index\z/',
            'server_uuid' => '/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/',
            default => null,
        };
        if ($pattern !== null) {
            return preg_match($pattern, $value) === 1;
        }

        return in_array($name, ['pseudo_thread_id', 'statement_id'], true) ? self::unsigned($value) : null;
    }

    /**
     * Keeps engine names, row order, metadata, logical monitor counters and Performance
     * Schema allocations exact; the physical InnoDB monitor sections are checked against
     * their content domain. Neither report strings nor rows are discarded.
     *
     * @param array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null} $expected
     * @param array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null} $actual
     */
    public static function engines(array $expected, array $actual): bool
    {
        foreach (['columns', 'affected', 'insertId', 'warnings', 'info'] as $field) {
            if ($expected[$field] !== $actual[$field]) {
                return false;
            }
        }
        if (count($expected['rows']) !== count($actual['rows'])) {
            return false;
        }
        foreach ($expected['rows'] as $index => $row) {
            $other = $actual['rows'][$index];
            if (count($row) !== 3 || count($other) !== 3 || $row[0] !== $other[0] || $row[1] !== $other[1] || !is_string($row[2]) || !is_string($other[2])) {
                return false;
            }
            if ($row[0] === 'InnoDB' && $row[1] === '') {
                $logical = InnoDbStatus::logical($row[2]);
                if ($logical === null || $logical !== InnoDbStatus::logical($other[2])) {
                    return false;
                }
            } elseif ($row !== $other) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validates the values observed to differ between two fresh native servers given the same
     * SQL history: physical I/O, the last query cost, process clocks, certificate validity and the
     * generated RSA keys. Every other name returns null and compares exactly.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/server-status-variables.html,
     * https://dev.mysql.com/doc/refman/8.4/en/server-status-variable-reference.html.
     */
    public static function environment(string $name, string $value): ?bool
    {
        if (in_array($name, explode(' ', self::ENVIRONMENT_COUNTERS), true)) {
            return self::unsigned($value);
        }
        $pattern = match ($name) {
            'Last_query_cost' => '/\A[0-9]+\.[0-9]{6}\z/',
            'Max_used_connections_time' => '/\A[0-9]{4}-(?:0[1-9]|1[0-2])-(?:0[1-9]|[12][0-9]|3[01]) (?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]\z/',
            'Ssl_server_not_after', 'Ssl_server_not_before', 'Mysqlx_ssl_server_not_after', 'Mysqlx_ssl_server_not_before' => '/\A(?:(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) {1,2}(?:[1-9]|[12][0-9]|3[01]) (?:[01][0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9] [0-9]{4} GMT)?\z/',
            'Innodb_buffer_pool_load_status' => '/\A(?:Buffer pool\(s\) load completed at [0-9]{6} {1,2}[0-9]{1,2}:[0-5][0-9]:[0-5][0-9]|Loading buffer pool\(s\) from [^\x00-\x1F]+|not started)?\z/',
            default => null,
        };
        if ($pattern !== null) {
            return preg_match($pattern, $value) === 1;
        }
        if ($name === 'Rsa_public_key' || $name === 'Caching_sha2_password_rsa_public_key') {
            if ($value === '') {
                return true;
            }
            if (preg_match('/\A-----BEGIN PUBLIC KEY-----\n([A-Za-z0-9+\/=\n]+)-----END PUBLIC KEY-----\n?\z/', $value, $match) !== 1) {
                return false;
            }
            $der = base64_decode(str_replace("\n", '', $match[1]), true);
            return $der !== false && strlen($der) >= 32 && $der[0] === "\x30";
        }
        return null;
    }

    /**
     * Answers whether an observed reply counter has MySQL's unsigned 64-bit text domain.
     * Status comparisons use this check without converting to PHP integers.
     */
    public static function unsigned(string $value): bool
    {
        return preg_match('/\A(?:0|[1-9][0-9]{0,19})\z/', $value) === 1
            && (strlen($value) < 20 || strcmp($value, '18446744073709551615') <= 0);
    }
}
