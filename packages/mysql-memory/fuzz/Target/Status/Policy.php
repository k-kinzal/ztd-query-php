<?php

declare(strict_types=1);

namespace Fuzz\Target\Status;

/**
 * Explicit comparison rules for isolated, complete SHOW STATUS observations.
 * Command counters retain SQL history. Engine access paths, buffers, files, TLS,
 * instrumentation and optimizer work have no physical counterpart in memory.
 * Unknown variables retain their exact value; a new catalog entry is not exempted
 * merely because two readings differ.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-status-variables.html.
 */
final class Policy
{
    /**
     * Classifies the documented quantity independently of its observed value.
     */
    public function kind(string $name, bool $global): string
    {
        if (str_starts_with($name, 'Com_') || $name === 'Questions') {
            return $global ? 'history' : 'exact';
        }
        if (in_array($name, ['Connections', 'Queries', 'Flush_commands', 'Aborted_clients', 'Deprecated_use_i_s_processlist_count'], true)) {
            return 'history';
        }
        if (in_array($name, ['Bytes_received', 'Bytes_sent'], true)) {
            return 'wire';
        }
        if (in_array($name, ['Uptime', 'Uptime_since_flush_status'], true)) {
            return 'clock';
        }
        if ($name === 'Deprecated_use_i_s_processlist_last_timestamp') {
            return 'event-time';
        }
        if ($name === 'Max_used_connections_time') {
            return 'connection-time';
        }
        if (in_array($name, ['Innodb_buffer_pool_dump_status', 'Innodb_page_size', 'Innodb_have_atomic_builtins', 'Innodb_redo_log_enabled', 'Innodb_redo_log_read_only', 'Innodb_redo_log_resize_status', 'Ssl_session_cache_mode', 'Ssl_session_cache_size', 'Ssl_session_cache_timeout', 'Mysqlx_port'], true)) {
            return 'exact';
        }
        if (str_starts_with($name, 'Current_tls_') || in_array($name, ['Caching_sha2_password_rsa_public_key', 'Rsa_public_key', 'Sha256_password_rsa_public_key', 'Innodb_buffer_pool_load_status', 'Innodb_buffer_pool_resize_status', 'Mysqlx_compression_algorithm', 'Mysqlx_compression_level', 'Mysqlx_ssl_active', 'Mysqlx_ssl_cipher_list', 'Mysqlx_ssl_verify_depth', 'Mysqlx_ssl_verify_mode', 'Mysqlx_address', 'Mysqlx_socket', 'Mysqlx_ssl_cipher', 'Mysqlx_ssl_version', 'Mysqlx_ssl_server_not_before', 'Mysqlx_ssl_server_not_after', 'Ssl_cipher', 'Ssl_cipher_list', 'Ssl_version', 'Ssl_server_not_before', 'Ssl_server_not_after', 'Tls_library_version'], true)) {
            return 'empty';
        }
        foreach (['Innodb_', 'Error_log_', 'Handler_', 'Key_', 'Performance_schema_', 'Qcache_', 'Binlog_cache_', 'Binlog_stmt_cache_', 'Created_tmp_', 'Select_', 'Sort_', 'Open_', 'Opened_', 'Table_open_cache_', 'Table_locks_', 'Mysqlx_', 'Ssl_'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return 'physical';
            }
        }

        return in_array($name, ['Threads_cached', 'Threads_created', 'Last_query_cost', 'Last_query_partial_plans'], true) ? 'physical' : 'exact';
    }

    /**
     * Returns a validated quantity, or null to retain the entire raw observation.
     */
    public function value(string $name, string $value, bool $global, bool $memory, Snapshot $before, string $sql, float $elapsed): ?string
    {
        $kind = $this->kind($name, $global);
        if ($kind === 'exact') {
            return $value;
        }
        if ($kind === 'empty') {
            return !$memory || $value === '' ? '<unavailable-physical-value>' : null;
        }
        if ($kind === 'physical') {
            return preg_match($memory ? '/\A0+(?:\.0+)?\z/' : '/\A[0-9]+(?:\.[0-9]+)?\z/', $value) === 1 ? '<physical-quantity>' : null;
        }
        $base = ($kind === 'wire' && !$global ? $before->session : $before->global)[$name] ?? null;
        if ($kind === 'connection-time') {
            return $value === $base && preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/', $value) === 1 ? '<unchanged-connection-maximum-time>' : null;
        }
        if ($base === null || !ctype_digit($base) || !ctype_digit($value) || bccomp($base, '18446744073709551615') > 0 || bccomp($value, '18446744073709551615') > 0) {
            return null;
        }
        if ($kind === 'event-time') {
            return $this->eventTime($value, $base, $before);
        }
        $difference = bcsub($value, $base);
        if ($kind === 'clock') {
            $seconds = bcmod(bcadd($difference, '18446744073709551616'), '18446744073709551616');

            return bccomp($seconds, (string) (int) ceil($elapsed + 1)) <= 0 ? '<bounded-elapsed-time>' : null;
        }
        if ($kind === 'wire') {
            $bytes = $name === 'Bytes_sent' ? ($global ? $before->globalBytes : $before->sessionBytes) : (string) (strlen($sql) + 5 + ($global ? strlen('SHOW SESSION STATUS') + 5 : 0));

            return ctype_digit($bytes) && bccomp($difference, $bytes) === 0 ? '<measured-wire-bytes>' : null;
        }

        return bccomp($difference, '0') >= 0 ? $difference : null;
    }

    /**
     * A status listing cannot move a process-list event's clock. Zero means no read since startup.
     * Different instance histories may have different clocks, but must obey that relationship.
     */
    public function eventTime(string $value, string $base, Snapshot $before): ?string
    {
        $count = $before->global['Deprecated_use_i_s_processlist_count'] ?? null;
        if (!ctype_digit($value) || $value !== $base || $count === null || !ctype_digit($count) || bccomp($count, '18446744073709551615') > 0) {
            return null;
        }

        return (bccomp($count, '0') === 0) === (bccomp($value, '0') === 0) ? '<unchanged-server-event-time>' : null;
    }
}
