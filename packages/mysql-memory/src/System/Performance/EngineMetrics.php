<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use SqlSemantics\Contract\GrammarRelease;

/**
 * The release-specific Performance Schema storage metrics, with no native instrumentation buffers allocated.
 * Names come from pfs_show_status in each release's storage/perfschema/ha_perfschema.cc
 * (pfs_engine_table.cc in 5.6/5.7). These are interface definitions, not captured server state.
 * Counts, byte sizes and memory totals are zero because this implementation materializes its
 * Performance Schema tables on demand rather than allocating the native engine's buffers.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engine.html.
 *
 * @visibility MySqlMemory
 */
final class EngineMetrics
{
    /** @var list<array{list<string>, string}> Releases and metric names. */
    private const GROUPS = [
        [['mysql-5.6.51'],
            'events_waits_current.row_size events_waits_current.row_count events_waits_history.row_size '
            . 'events_waits_history.row_count events_waits_history_long.row_size events_waits_history_long.row_count '
            . '(pfs_mutex_class).row_size (pfs_mutex_class).row_count (pfs_rwlock_class).row_size '
            . '(pfs_rwlock_class).row_count (pfs_cond_class).row_size (pfs_cond_class).row_count (pfs_thread_class).row_size '
            . '(pfs_thread_class).row_count (pfs_file_class).row_size (pfs_file_class).row_count mutex_instances.row_size '
            . 'mutex_instances.row_count rwlock_instances.row_size rwlock_instances.row_count cond_instances.row_size '
            . 'cond_instances.row_count threads.row_size threads.row_count file_instances.row_size file_instances.row_count '
            . '(pfs_file_handle).row_size (pfs_file_handle).row_count events_waits_summary_by_thread_by_event_name.row_size '
            . 'events_waits_summary_by_thread_by_event_name.row_count (pfs_table_share).row_size (pfs_table_share).row_count '
            . '(pfs_table).row_size (pfs_table).row_count setup_actors.row_size setup_actors.row_count setup_objects.row_size '
            . 'setup_objects.row_count (pfs_account).row_size (pfs_account).row_count '
            . 'events_waits_summary_by_account_by_event_name.row_size events_waits_summary_by_account_by_event_name.row_count '
            . 'events_waits_summary_by_user_by_event_name.row_size events_waits_summary_by_user_by_event_name.row_count '
            . 'events_waits_summary_by_host_by_event_name.row_size events_waits_summary_by_host_by_event_name.row_count '
            . '(pfs_user).row_size (pfs_user).row_count (pfs_host).row_size (pfs_host).row_count (pfs_stage_class).row_size '
            . '(pfs_stage_class).row_count events_stages_history.row_size events_stages_history.row_count '
            . 'events_stages_history_long.row_size events_stages_history_long.row_count '
            . 'events_stages_summary_by_thread_by_event_name.row_size events_stages_summary_by_thread_by_event_name.row_count '
            . 'events_stages_summary_global_by_event_name.row_size events_stages_summary_global_by_event_name.row_count '
            . 'events_stages_summary_by_account_by_event_name.row_size '
            . 'events_stages_summary_by_account_by_event_name.row_count events_stages_summary_by_user_by_event_name.row_size '
            . 'events_stages_summary_by_user_by_event_name.row_count events_stages_summary_by_host_by_event_name.row_size '
            . 'events_stages_summary_by_host_by_event_name.row_count (pfs_statement_class).row_size '
            . '(pfs_statement_class).row_count events_statements_history.row_size events_statements_history.row_count '
            . 'events_statements_history_long.row_size events_statements_history_long.row_count '
            . 'events_statements_summary_by_thread_by_event_name.row_size '
            . 'events_statements_summary_by_thread_by_event_name.row_count '
            . 'events_statements_summary_global_by_event_name.row_size '
            . 'events_statements_summary_global_by_event_name.row_count '
            . 'events_statements_summary_by_account_by_event_name.row_size '
            . 'events_statements_summary_by_account_by_event_name.row_count '
            . 'events_statements_summary_by_user_by_event_name.row_size '
            . 'events_statements_summary_by_user_by_event_name.row_count '
            . 'events_statements_summary_by_host_by_event_name.row_size '
            . 'events_statements_summary_by_host_by_event_name.row_count events_statements_current.row_size '
            . 'events_statements_current.row_count (pfs_socket_class).row_size (pfs_socket_class).row_count '
            . 'socket_instances.row_size socket_instances.row_count events_statements_summary_by_digest.row_size '
            . 'events_statements_summary_by_digest.row_count session_connect_attrs.row_size session_connect_attrs.row_count '
            . '(history_long_statements_digest_token_array).row_count (history_long_statements_digest_token_array).row_size '
            . '(history_statements_digest_token_array).row_count (history_statements_digest_token_array).row_size '
            . '(current_statements_digest_token_array).row_count (current_statements_digest_token_array).row_size '
            . '(statements_digest_token_array).row_count (statements_digest_token_array).row_size',
        ],
        [['mysql-5.6.51','mysql-5.7.44','mysql-8.0.44','mysql-8.1.0','mysql-8.2.0','mysql-8.3.0','mysql-8.4.7','mysql-9.0.1','mysql-9.1.0'],
            'events_waits_history.memory events_waits_history_long.memory (pfs_mutex_class).memory '
            . '(pfs_rwlock_class).memory (pfs_cond_class).memory (pfs_thread_class).memory (pfs_file_class).memory '
            . 'mutex_instances.memory rwlock_instances.memory cond_instances.memory threads.memory file_instances.memory '
            . '(pfs_file_handle).memory events_waits_summary_by_thread_by_event_name.memory (pfs_table_share).memory '
            . '(pfs_table).memory setup_actors.memory setup_objects.memory (pfs_account).memory '
            . 'events_waits_summary_by_account_by_event_name.memory events_waits_summary_by_user_by_event_name.memory '
            . 'events_waits_summary_by_host_by_event_name.memory (pfs_user).memory (pfs_host).memory (pfs_stage_class).memory '
            . 'events_stages_history.memory events_stages_history_long.memory '
            . 'events_stages_summary_by_thread_by_event_name.memory events_stages_summary_global_by_event_name.memory '
            . 'events_stages_summary_by_account_by_event_name.memory events_stages_summary_by_user_by_event_name.memory '
            . 'events_stages_summary_by_host_by_event_name.memory (pfs_statement_class).memory '
            . 'events_statements_history.memory events_statements_history_long.memory '
            . 'events_statements_summary_by_thread_by_event_name.memory events_statements_summary_global_by_event_name.memory '
            . 'events_statements_summary_by_account_by_event_name.memory '
            . 'events_statements_summary_by_user_by_event_name.memory events_statements_summary_by_host_by_event_name.memory '
            . 'events_statements_current.memory (pfs_socket_class).memory socket_instances.memory '
            . 'events_statements_summary_by_digest.memory session_connect_attrs.memory (account_hash).count '
            . '(account_hash).size (digest_hash).count (digest_hash).size (filename_hash).count (filename_hash).size '
            . '(host_hash).count (host_hash).size (setup_actor_hash).count (setup_actor_hash).size (setup_object_hash).count '
            . '(setup_object_hash).size (table_share_hash).count (table_share_hash).size (user_hash).count (user_hash).size '
            . 'host_cache.size (history_long_statements_digest_token_array).memory '
            . '(history_statements_digest_token_array).memory (current_statements_digest_token_array).memory '
            . '(statements_digest_token_array).memory performance_schema.memory',
        ],
        [['mysql-5.7.44','mysql-8.0.44','mysql-8.1.0','mysql-8.2.0','mysql-8.3.0','mysql-8.4.7','mysql-9.0.1','mysql-9.1.0'],
            'events_waits_current.size events_waits_current.count events_waits_history.size events_waits_history.count '
            . 'events_waits_history_long.size events_waits_history_long.count (pfs_mutex_class).size (pfs_mutex_class).count '
            . '(pfs_rwlock_class).size (pfs_rwlock_class).count (pfs_cond_class).size (pfs_cond_class).count '
            . '(pfs_thread_class).size (pfs_thread_class).count (pfs_file_class).size (pfs_file_class).count '
            . 'mutex_instances.size mutex_instances.count rwlock_instances.size rwlock_instances.count cond_instances.size '
            . 'cond_instances.count threads.size threads.count file_instances.size file_instances.count '
            . '(pfs_file_handle).size (pfs_file_handle).count events_waits_summary_by_thread_by_event_name.size '
            . 'events_waits_summary_by_thread_by_event_name.count (pfs_table_share).size (pfs_table_share).count '
            . '(pfs_table).size (pfs_table).count setup_actors.size setup_actors.count setup_objects.size setup_objects.count '
            . '(pfs_account).size (pfs_account).count events_waits_summary_by_account_by_event_name.size '
            . 'events_waits_summary_by_account_by_event_name.count events_waits_summary_by_user_by_event_name.size '
            . 'events_waits_summary_by_user_by_event_name.count events_waits_summary_by_host_by_event_name.size '
            . 'events_waits_summary_by_host_by_event_name.count (pfs_user).size (pfs_user).count (pfs_host).size '
            . '(pfs_host).count (pfs_stage_class).size (pfs_stage_class).count events_stages_history.size '
            . 'events_stages_history.count events_stages_history_long.size events_stages_history_long.count '
            . 'events_stages_summary_by_thread_by_event_name.size events_stages_summary_by_thread_by_event_name.count '
            . 'events_stages_summary_global_by_event_name.size events_stages_summary_global_by_event_name.count '
            . 'events_stages_summary_by_account_by_event_name.size events_stages_summary_by_account_by_event_name.count '
            . 'events_stages_summary_by_user_by_event_name.size events_stages_summary_by_user_by_event_name.count '
            . 'events_stages_summary_by_host_by_event_name.size events_stages_summary_by_host_by_event_name.count '
            . '(pfs_statement_class).size (pfs_statement_class).count events_statements_history.size '
            . 'events_statements_history.count events_statements_history_long.size events_statements_history_long.count '
            . 'events_statements_summary_by_thread_by_event_name.size events_statements_summary_by_thread_by_event_name.count '
            . 'events_statements_summary_global_by_event_name.size events_statements_summary_global_by_event_name.count '
            . 'events_statements_summary_by_account_by_event_name.size '
            . 'events_statements_summary_by_account_by_event_name.count events_statements_summary_by_user_by_event_name.size '
            . 'events_statements_summary_by_user_by_event_name.count events_statements_summary_by_host_by_event_name.size '
            . 'events_statements_summary_by_host_by_event_name.count events_statements_current.size '
            . 'events_statements_current.count (pfs_socket_class).size (pfs_socket_class).count socket_instances.size '
            . 'socket_instances.count events_statements_summary_by_digest.size events_statements_summary_by_digest.count '
            . 'events_statements_summary_by_program.size events_statements_summary_by_program.count '
            . 'events_statements_summary_by_program.memory session_connect_attrs.size session_connect_attrs.count '
            . 'prepared_statements_instances.size prepared_statements_instances.count prepared_statements_instances.memory '
            . '(program_hash).count (program_hash).size (pfs_memory_class).row_size (pfs_memory_class).row_count '
            . '(pfs_memory_class).memory memory_summary_by_thread_by_event_name.row_size '
            . 'memory_summary_by_thread_by_event_name.row_count memory_summary_by_thread_by_event_name.memory '
            . 'memory_summary_global_by_event_name.row_size memory_summary_global_by_event_name.row_count '
            . 'memory_summary_global_by_event_name.memory memory_summary_by_account_by_event_name.row_size '
            . 'memory_summary_by_account_by_event_name.row_count memory_summary_by_account_by_event_name.memory '
            . 'memory_summary_by_user_by_event_name.row_size memory_summary_by_user_by_event_name.row_count '
            . 'memory_summary_by_user_by_event_name.memory memory_summary_by_host_by_event_name.row_size '
            . 'memory_summary_by_host_by_event_name.row_count memory_summary_by_host_by_event_name.memory '
            . 'metadata_locks.row_size metadata_locks.row_count metadata_locks.memory events_transactions_history.size '
            . 'events_transactions_history.count events_transactions_history.memory events_transactions_history_long.size '
            . 'events_transactions_history_long.count events_transactions_history_long.memory '
            . 'events_transactions_summary_by_thread_by_event_name.size '
            . 'events_transactions_summary_by_thread_by_event_name.count '
            . 'events_transactions_summary_by_thread_by_event_name.memory '
            . 'events_transactions_summary_by_account_by_event_name.size '
            . 'events_transactions_summary_by_account_by_event_name.count '
            . 'events_transactions_summary_by_account_by_event_name.memory '
            . 'events_transactions_summary_by_user_by_event_name.size events_transactions_summary_by_user_by_event_name.count '
            . 'events_transactions_summary_by_user_by_event_name.memory '
            . 'events_transactions_summary_by_host_by_event_name.size events_transactions_summary_by_host_by_event_name.count '
            . 'events_transactions_summary_by_host_by_event_name.memory table_lock_waits_summary_by_table.size '
            . 'table_lock_waits_summary_by_table.count table_lock_waits_summary_by_table.memory '
            . 'table_io_waits_summary_by_index_usage.size table_io_waits_summary_by_index_usage.count '
            . 'table_io_waits_summary_by_index_usage.memory (history_long_statements_digest_token_array).count '
            . '(history_long_statements_digest_token_array).size (history_statements_digest_token_array).count '
            . '(history_statements_digest_token_array).size (current_statements_digest_token_array).count '
            . '(current_statements_digest_token_array).size (history_long_statements_text_array).count '
            . '(history_long_statements_text_array).size (history_long_statements_text_array).memory '
            . '(history_statements_text_array).count (history_statements_text_array).size '
            . '(history_statements_text_array).memory (current_statements_text_array).count '
            . '(current_statements_text_array).size (current_statements_text_array).memory '
            . '(statements_digest_token_array).count (statements_digest_token_array).size',
        ],
        [['mysql-8.0.44','mysql-8.1.0','mysql-8.2.0','mysql-8.3.0','mysql-8.4.7','mysql-9.0.1','mysql-9.1.0'],
            'events_errors_summary_by_thread_by_error.size events_errors_summary_by_thread_by_error.count '
            . 'events_errors_summary_by_thread_by_error.memory events_errors_summary_by_account_by_error.size '
            . 'events_errors_summary_by_account_by_error.count events_errors_summary_by_account_by_error.memory '
            . 'events_errors_summary_by_user_by_error.size events_errors_summary_by_user_by_error.count '
            . 'events_errors_summary_by_user_by_error.memory events_errors_summary_by_host_by_error.size '
            . 'events_errors_summary_by_host_by_error.count events_errors_summary_by_host_by_error.memory '
            . 'events_errors_summary_global_by_error.size events_errors_summary_global_by_error.count '
            . 'events_errors_summary_global_by_error.memory (pfs_buffer_scalable_container).count '
            . '(pfs_buffer_scalable_container).memory (max_global_server_errors).count (max_session_server_errors).count',
        ],
        [['mysql-8.2.0','mysql-8.3.0','mysql-8.4.7','mysql-9.0.1','mysql-9.1.0'],
            '(pfs_meter_class).size (pfs_meter_class).count (pfs_meter_class).memory (pfs_metric_class).size '
            . '(pfs_metric_class).count (pfs_metric_class).memory',
        ],
        [['mysql-9.1.0'],
            '(pfs_logger_class).size (pfs_logger_class).count (pfs_logger_class).memory',
        ],
    ];

    /**
     * @return list<array{string, string, string}>
     */
    public function rows(GrammarRelease $release): array
    {
        $rows = [];
        foreach (self::GROUPS as [$releases, $names]) {
            if (in_array($release->value, $releases, true)) {
                foreach (explode(' ', $names) as $name) {
                    $rows[] = ['performance_schema', $name, '0'];
                }
            }
        }
        return $rows;
    }
}
