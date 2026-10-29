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
 * Bare SHOW STATUS preserves every row and field. Named environment-dependent
 * values are checked against their content domains instead of literal equality;
 * session command counters, other values and all reply metadata still compare exactly.
 * SHOW ENGINE ... MUTEX validates every returned physical-latch record; the
 * set of latches with OS waits belongs to each process, so its cardinality varies.
 */
final class ExecutionTarget
{
    /**
     * Physical-engine and process-environment counters with an unsigned 64-bit text domain.
     * Global Queries and Flush_commands include native server startup activity.
     */
    private const ENVIRONMENT_COUNTERS = 'Binlog_cache_disk_use Binlog_cache_use Binlog_stmt_cache_disk_use Binlog_stmt_cache_use '
        . 'Bytes_received Bytes_sent Connections Created_tmp_disk_tables Created_tmp_files Created_tmp_tables '
        . 'Error_log_buffered_bytes Error_log_buffered_events Error_log_expired_events Error_log_latest_write Flush_commands '
        . 'Handler_commit Handler_delete Handler_discover Handler_external_lock Handler_mrr_init Handler_prepare '
        . 'Handler_read_first Handler_read_key Handler_read_last Handler_read_next Handler_read_prev Handler_read_rnd '
        . 'Handler_read_rnd_next Handler_rollback Handler_savepoint Handler_savepoint_rollback Handler_update Handler_write '
        . 'Innodb_buffer_pool_bytes_data Innodb_buffer_pool_bytes_dirty Innodb_buffer_pool_pages_data '
        . 'Innodb_buffer_pool_pages_dirty Innodb_buffer_pool_pages_flushed Innodb_buffer_pool_pages_free '
        . 'Innodb_buffer_pool_pages_misc Innodb_buffer_pool_pages_total Innodb_buffer_pool_read_ahead '
        . 'Innodb_buffer_pool_read_ahead_evicted Innodb_buffer_pool_read_ahead_rnd Innodb_buffer_pool_read_requests '
        . 'Innodb_buffer_pool_reads Innodb_buffer_pool_wait_free Innodb_buffer_pool_write_requests '
        . 'Innodb_data_fsyncs Innodb_data_pending_fsyncs Innodb_data_pending_reads Innodb_data_pending_writes '
        . 'Innodb_data_read Innodb_data_reads Innodb_data_writes Innodb_data_written Innodb_dblwr_pages_written '
        . 'Innodb_dblwr_writes Innodb_log_waits Innodb_log_write_requests Innodb_log_writes Innodb_num_open_files '
        . 'Innodb_os_log_fsyncs Innodb_os_log_pending_fsyncs Innodb_os_log_pending_writes Innodb_os_log_written '
        . 'Innodb_pages_created Innodb_pages_read Innodb_pages_written Innodb_redo_log_capacity_resized '
        . 'Innodb_redo_log_checkpoint_lsn Innodb_redo_log_current_lsn Innodb_redo_log_flushed_to_disk_lsn '
        . 'Innodb_redo_log_logical_size Innodb_redo_log_physical_size Innodb_redo_log_uuid '
        . 'Innodb_system_rows_deleted Innodb_system_rows_inserted Innodb_system_rows_read Innodb_system_rows_updated '
        . 'Innodb_undo_tablespaces_active Innodb_undo_tablespaces_explicit Innodb_undo_tablespaces_implicit Innodb_undo_tablespaces_total '
        . 'Key_blocks_not_flushed Key_blocks_unused Key_blocks_used Key_read_requests Key_reads Key_write_requests Key_writes '
        . 'Last_query_partial_plans Max_used_connections Mysqlx_ssl_ctx_verify_depth Mysqlx_ssl_ctx_verify_mode Mysqlx_worker_threads Mysqlx_worker_threads_active '
        . 'Open_files Open_streams Open_table_definitions Open_tables Opened_files Opened_table_definitions Opened_tables '
        . 'Performance_schema_session_connect_attrs_longest_seen Queries Select_full_join Select_full_range_join Select_range '
        . 'Select_range_check Select_scan Sort_merge_passes Sort_range Sort_rows Sort_scan Ssl_ctx_verify_depth Ssl_ctx_verify_mode '
        . 'Table_locks_immediate Table_locks_waited Table_open_cache_hits Table_open_cache_misses Table_open_cache_overflows '
        . 'Threads_cached Threads_created Uptime Uptime_since_flush_status';

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
            } elseif (preg_match('/\ASHOW\s+ENGINE\s+(?:ALL|INNODB|`INNODB`)\s+MUTEX\s*;?\s*\z/i', trim($sql)) === 1) {
                $matching = self::latches($expected['results'][0], $actual['results'][0], $this->server->version === '5.6.51');
            } elseif (preg_match('/\ASHOW\s+ENGINE\s+(?:ALL|INNODB|PERFORMANCE_SCHEMA|`INNODB`|`PERFORMANCE_SCHEMA`)\s+STATUS\s*;?\s*\z/i', trim($sql)) === 1) {
                $matching = self::engines($expected['results'][0], $actual['results'][0]);
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
            $name = $global && in_array($row[0], explode(' ', self::STARTUP_COUNTERS), true) ? 'Queries' : $row[0];
            $valid = self::environment($name, $row[1]);
            if ($valid === null ? $row[1] !== $other[1] : (!$valid || self::environment($name, $other[1]) !== true)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Keeps engine names, row order, metadata and logical monitor counters exact.
     * Every physical monitor section and Performance Schema allocation is checked
     * against its content domain; neither report strings nor rows are discarded.
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
            } elseif ($row[0] === 'performance_schema' && is_string($row[1]) && preg_match('/\A(?:[a-z0-9_]+|\([a-z0-9_]+\))\.(?:size|count|memory|row_size|row_count)\z/', $row[1]) === 1) {
                if (self::environment('Uptime', $row[2]) !== true || self::environment('Uptime', $other[2]) !== true) {
                    return false;
                }
            } elseif ($row !== $other) {
                return false;
            }
        }
        return true;
    }

    /**
     * Validates physical I/O, optimizer work, process-wide startup activity,
     * clocks, and installed TLS/X Plugin configuration. These vary between
     * processes even when they receive the same SQL history. No SQL-visible
     * command counter or logical InnoDB user-row count is included.
     * Empty TLS values mean that the emulated endpoint has no TLS configuration.
     * Unknown names return null and retain exact comparison.
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
            'Current_tls_ca', 'Current_tls_capath', 'Current_tls_cert', 'Current_tls_key', 'Current_tls_crl', 'Current_tls_crlpath', 'Mysqlx_socket' => '/\A[^\x00-\x1F\x7F]*\z/',
            'Current_tls_version', 'Ssl_version', 'Mysqlx_ssl_version' => '/\A(?:TLSv1(?:\.[0-3])?(?:,TLSv1(?:\.[0-3])?)*)?\z/',
            'Current_tls_cipher', 'Current_tls_ciphersuites', 'Ssl_cipher', 'Ssl_cipher_list', 'Mysqlx_ssl_cipher', 'Mysqlx_ssl_cipher_list' => '/\A[A-Za-z0-9_!:+@=.\-]*\z/',
            'Tls_library_version' => '/\A(?:(?:OpenSSL|LibreSSL|wolfSSL|yaSSL) [A-Za-z0-9 .+()\-]+)?\z/',
            'Innodb_buffer_pool_load_status' => '/\A(?:Buffer pool\(s\) load completed at [0-9]{6} {1,2}[0-9]{1,2}:[0-5][0-9]:[0-5][0-9]|Loading buffer pool\(s\) from [^\x00-\x1F]+|not started)?\z/',
            default => null,
        };
        if ($pattern !== null) {
            return preg_match($pattern, $value) === 1;
        }
        if ($name === 'Mysqlx_address') {
            return $value === '' || $value === '*' || filter_var($value, FILTER_VALIDATE_IP) !== false;
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
     * Checks the physical latches each process actually reports. Native InnoDB
     * emits only mutexes with spins/waits and rwlocks with OS waits. Which latches
     * appear, how many appear and their source locations depend on the server
     * build and scheduling, not just SQL history. An empty set is valid when no
     * latches waited. Every reported row is validated without removal or merging;
     * metadata and completion fields compare exactly, and affected rows must
     * equal each response's own row count.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engine.html,
     * https://github.com/mysql/mysql-server/blob/mysql-8.4.7/storage/innobase/handler/ha_innodb.cc.
     *
     * @param array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null} $expected
     * @param array{columns: list<array<string, mixed>>, rows: list<list<mixed>>, affected: int|string, insertId: int|string, warnings: int, info: string|null} $actual
     */
    public static function latches(array $expected, array $actual, bool $legacy): bool
    {
        foreach (['columns', 'insertId', 'warnings', 'info'] as $field) {
            if ($expected[$field] !== $actual[$field]) {
                return false;
            }
        }
        if ($expected['affected'] !== count($expected['rows']) || $actual['affected'] !== count($actual['rows'])) {
            return false;
        }
        return self::latchRows($expected['rows'], $legacy) && self::latchRows($actual['rows'], $legacy);
    }

    /**
     * Validates release-specific mutex and rwlock names and nonzero wait/spin counts.
     * MySQL 5.6's non-debug build reports os_waits; later releases distinguish
     * mutex counters from rwlock waits. Duplicate names represent different latches.
     *
     * @param list<list<mixed>> $rows
     */
    public static function latchRows(array $rows, bool $legacy): bool
    {
        foreach ($rows as $row) {
            if (count($row) !== 3 || $row[0] !== 'InnoDB' || !is_string($row[1]) || !is_string($row[2])) {
                return false;
            }
            $location = $legacy ? '/\A(?:combined )?[A-Za-z0-9_.-]+:[1-9][0-9]*\z/' : '/\A(?:sum )?rwlock: [A-Za-z0-9_.-]+:[1-9][0-9]*\z/';
            if (preg_match($location, $row[1]) === 1) {
                $pattern = $legacy ? '/\Aos_waits=([1-9][0-9]*)\z/' : '/\Awaits=([1-9][0-9]*)\z/';
                if (preg_match($pattern, $row[2], $match) !== 1 || !self::unsigned($match[1])) {
                    return false;
                }
            } elseif (!$legacy && preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $row[1]) === 1) {
                if (preg_match('/\Aspins=([0-9]+),waits=([0-9]+),calls=([0-9]+)\z/', $row[2], $match) !== 1 || !self::unsigned($match[1]) || !self::unsigned($match[2]) || !self::unsigned($match[3]) || ($match[1] === '0' && $match[2] === '0')) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Answers whether an observed reply counter has MySQL's unsigned 64-bit text domain.
     * Status and latch comparisons share this check without converting to PHP integers.
     */
    public static function unsigned(string $value): bool
    {
        return preg_match('/\A(?:0|[1-9][0-9]{0,19})\z/', $value) === 1
            && (strlen($value) < 20 || strcmp($value, '18446744073709551615') <= 0);
    }
}
