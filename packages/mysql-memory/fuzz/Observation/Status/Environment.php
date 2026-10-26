<?php

declare(strict_types=1);

namespace Fuzz\Observation\Status;

/**
 * Only documented environment-dependent status values receive domain validation instead of equality.
 * Names, presence, column metadata, SQL counters, configuration switches and unknown variables stay exact.
 * Both engines use the same validators; an empty value is legal only for explicitly optional text fields.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/server-status-variables.html.
 * Buffer-load states: https://dev.mysql.com/doc/refman/8.0/en/innodb-preload-buffer-pool.html.
 */
final class Environment
{
    /** @var list<string> Named quantities, grouped by their environmental cause. */
    private const COUNTERS = [
        // Buffer allocation, disk I/O and redo positions depend on the physical engine and its history.
        'Innodb_buffer_pool_bytes_data Innodb_buffer_pool_bytes_dirty Innodb_buffer_pool_pages_data '
        . 'Innodb_buffer_pool_pages_dirty Innodb_buffer_pool_pages_flushed Innodb_buffer_pool_pages_free '
        . 'Innodb_buffer_pool_pages_misc Innodb_buffer_pool_pages_total Innodb_buffer_pool_read_ahead '
        . 'Innodb_buffer_pool_read_ahead_evicted Innodb_buffer_pool_read_ahead_rnd Innodb_buffer_pool_read_requests '
        . 'Innodb_buffer_pool_reads Innodb_buffer_pool_wait_free Innodb_buffer_pool_write_requests Innodb_data_fsyncs '
        . 'Innodb_data_pending_fsyncs Innodb_data_pending_reads Innodb_data_pending_writes Innodb_data_read '
        . 'Innodb_data_reads Innodb_data_writes Innodb_data_written Innodb_dblwr_pages_written Innodb_dblwr_writes '
        . 'Innodb_log_waits Innodb_log_write_requests Innodb_log_writes Innodb_num_open_files Innodb_os_log_fsyncs '
        . 'Innodb_os_log_pending_fsyncs Innodb_os_log_pending_writes Innodb_os_log_written Innodb_pages_created '
        . 'Innodb_pages_read Innodb_pages_written Innodb_row_lock_current_waits Innodb_row_lock_time '
        . 'Innodb_row_lock_time_avg Innodb_row_lock_time_max Innodb_row_lock_waits Innodb_rows_deleted '
        . 'Innodb_rows_inserted Innodb_rows_read Innodb_rows_updated Innodb_truncated_status_writes '
        . 'Innodb_available_undo_logs Innodb_redo_log_capacity_resized Innodb_redo_log_checkpoint_lsn '
        . 'Innodb_redo_log_current_lsn Innodb_redo_log_flushed_to_disk_lsn Innodb_redo_log_logical_size '
        . 'Innodb_redo_log_physical_size Innodb_redo_log_uuid Innodb_sampled_pages_read Innodb_sampled_pages_skipped '
        . 'Innodb_system_rows_deleted Innodb_system_rows_inserted Innodb_system_rows_read Innodb_system_rows_updated '
        . 'Innodb_undo_tablespaces_active Innodb_undo_tablespaces_explicit Innodb_undo_tablespaces_implicit '
        . 'Innodb_undo_tablespaces_total',
        // Cache, file, lock and access-path work depends on allocation and the optimizer.
        'Binlog_cache_disk_use Binlog_cache_use Binlog_stmt_cache_disk_use Binlog_stmt_cache_use '
        . 'Created_tmp_disk_tables Created_tmp_files Created_tmp_tables Handler_commit Handler_delete Handler_discover '
        . 'Handler_external_lock Handler_mrr_init Handler_prepare Handler_read_first Handler_read_key Handler_read_last '
        . 'Handler_read_next Handler_read_prev Handler_read_rnd Handler_read_rnd_next Handler_rollback Handler_savepoint '
        . 'Handler_savepoint_rollback Handler_update Handler_write Key_blocks_not_flushed Key_blocks_unused '
        . 'Key_blocks_used Key_read_requests Key_reads Key_write_requests Key_writes Open_files Open_streams '
        . 'Open_table_definitions Open_tables Opened_files Opened_table_definitions Opened_tables Qcache_free_blocks '
        . 'Qcache_free_memory Qcache_hits Qcache_inserts Qcache_lowmem_prunes Qcache_not_cached Qcache_queries_in_cache '
        . 'Qcache_total_blocks Select_full_join Select_full_range_join Select_range Select_range_check Select_scan '
        . 'Sort_merge_passes Sort_range Sort_rows Sort_scan Table_locks_immediate Table_locks_waited '
        . 'Table_open_cache_hits Table_open_cache_misses Table_open_cache_overflows Last_query_partial_plans',
        // Thread scheduling, instrumentation capacity and error-log history belong to the running process.
        'Threads_cached Threads_created Mysqlx_worker_threads Mysqlx_worker_threads_active Error_log_buffered_bytes '
        . 'Error_log_buffered_events Error_log_expired_events Error_log_latest_write Global_connection_memory '
        . 'Performance_schema_session_connect_attrs_longest_seen',
        // TLS counters measure process-wide transport activity, independently of SQL results.
        'Ssl_accept_renegotiates Ssl_accepts Ssl_callback_cache_hits Ssl_client_connects Ssl_connect_renegotiates '
        . 'Ssl_finished_accepts Ssl_finished_connects Ssl_session_cache_hits Ssl_session_cache_misses '
        . 'Ssl_session_cache_overflows Ssl_session_cache_timeouts Ssl_used_session_cache_entries Mysqlx_ssl_accepts '
        . 'Mysqlx_ssl_finished_accepts',
        // Lost instrumentation records depend on server instrumentation capacity.
        'Performance_schema_accounts_lost Performance_schema_cond_classes_lost Performance_schema_cond_instances_lost '
        . 'Performance_schema_digest_lost Performance_schema_file_classes_lost Performance_schema_file_handles_lost '
        . 'Performance_schema_file_instances_lost Performance_schema_hosts_lost Performance_schema_index_stat_lost '
        . 'Performance_schema_locker_lost Performance_schema_logger_lost Performance_schema_memory_classes_lost '
        . 'Performance_schema_metadata_lock_lost Performance_schema_meter_lost Performance_schema_metric_lost '
        . 'Performance_schema_mutex_classes_lost Performance_schema_mutex_instances_lost '
        . 'Performance_schema_nested_statement_lost Performance_schema_prepared_statements_lost '
        . 'Performance_schema_program_lost Performance_schema_rwlock_classes_lost '
        . 'Performance_schema_rwlock_instances_lost Performance_schema_session_connect_attrs_lost '
        . 'Performance_schema_socket_classes_lost Performance_schema_socket_instances_lost '
        . 'Performance_schema_stage_classes_lost Performance_schema_statement_classes_lost '
        . 'Performance_schema_table_handles_lost Performance_schema_table_instances_lost '
        . 'Performance_schema_table_lock_stat_lost Performance_schema_thread_classes_lost '
        . 'Performance_schema_thread_instances_lost Performance_schema_users_lost',
    ];

    /**
     * Answers the documented domain, or null when the value must compare exactly.
     */
    public function domain(string $name): ?string
    {
        foreach (self::COUNTERS as $names) {
            if (in_array($name, explode(' ', $names), true)) {
                return 'unsigned';
            }
        }
        return match ($name) {
            'Last_query_cost' => 'cost',
            'Caching_sha2_password_rsa_public_key', 'Rsa_public_key', 'Sha256_password_rsa_public_key' => 'public-key',
            'Ssl_server_not_before', 'Ssl_server_not_after', 'Mysqlx_ssl_server_not_before', 'Mysqlx_ssl_server_not_after' => 'certificate-time',
            'Ssl_ctx_verify_depth', 'Mysqlx_ssl_ctx_verify_depth' => 'unsigned',
            'Ssl_ctx_verify_mode', 'Mysqlx_ssl_ctx_verify_mode' => 'verification-mode',
            'Current_tls_ca', 'Current_tls_capath', 'Current_tls_cert', 'Current_tls_crl', 'Current_tls_crlpath', 'Current_tls_key', 'Mysqlx_socket' => 'path',
            'Current_tls_cipher', 'Current_tls_ciphersuites' => 'cipher-list',
            'Current_tls_version' => 'tls-versions',
            'Mysqlx_address' => 'listen-address',
            'Tls_library_version' => 'tls-library',
            'Innodb_buffer_pool_load_status' => 'buffer-load',
            default => null,
        };
    }

    /**
     * Preserves exact values outside the allowlist and rejects malformed values inside it.
     */
    public function value(string $name, string $value): ?string
    {
        $domain = $this->domain($name);
        if ($domain === null) {
            return $value;
        }
        $valid = match ($domain) {
            'unsigned' => ctype_digit($value) && bccomp($value, '18446744073709551615') <= 0,
            'cost' => preg_match('/\A[0-9]+(?:\.[0-9]+)?\z/', $value) === 1 && is_finite((float) $value),
            'verification-mode' => in_array($value, ['0', '1', '2', '3', '4', '5', '6', '7'], true),
            'public-key' => $value === '' || $this->publicKey($value),
            'certificate-time' => $value === '' || preg_match('/\A(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec) [ 0-3][0-9] [0-2][0-9]:[0-5][0-9]:[0-5][0-9] [0-9]{4} GMT\z/', $value) === 1,
            'path' => preg_match('/[\x00-\x1f\x7f]/', $value) === 0,
            'cipher-list' => $value === '' || preg_match('/\A[A-Za-z0-9_!+@=.-]+(?::[A-Za-z0-9_!+@=.-]+)*\z/', $value) === 1,
            'tls-versions' => $value === '' || preg_match('/\ATLSv1(?:\.[0-3])?(?:,TLSv1(?:\.[0-3])?)*\z/', $value) === 1,
            'listen-address' => $value === '' || $value === '*' || filter_var($value, FILTER_VALIDATE_IP) !== false,
            'tls-library' => $value === '' || preg_match('/\A(?:OpenSSL|LibreSSL|wolfSSL) [0-9]+\.[0-9]+\.[0-9]+[a-z]?(?: [A-Za-z0-9 -]+)?\z/', $value) === 1,
            'buffer-load' => $value === 'not started' || preg_match('/\ABuffer pool\(s\) load completed at [0-9]{6} [ 0-2][0-9]:[0-5][0-9]:[0-5][0-9]\z/', $value) === 1,
            default => false,
        };
        return $valid ? '<environment:' . $domain . '>' : null;
    }

    /**
     * Checks the PEM envelope and a DER sequence, without trusting arbitrary nonempty strings.
     */
    public function publicKey(string $value): bool
    {
        if (preg_match('/\A-----BEGIN PUBLIC KEY-----\n([A-Za-z0-9+\/=\n]+)-----END PUBLIC KEY-----\n?\z/', $value, $match) !== 1) {
            return false;
        }
        $der = base64_decode(str_replace("\n", '', $match[1]), true);
        return is_string($der) && strlen($der) > 32 && $der[0] === "\x30";
    }
}
