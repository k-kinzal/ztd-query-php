<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use RuntimeException;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The server registration order of SHOW STATUS names that do not follow alphabetical order.
 *
 * @visibility MySqlMemory
 */
final class StatusOrder
{
    /**
     * @var array<string, list<string>> Names registered before the indicated ordinary-sort anchor
     */
    private const MYSQL_5_6_51 = [
        'Com_alter_db' => ['Com_assign_to_keycache'],
        'Com_show_binlog_events' => ['Com_signal'],
        'Com_show_errors' => ['Com_show_events'],
        'Innodb_buffer_pool_pages_dirty' => ['Innodb_buffer_pool_bytes_data'],
        'Innodb_buffer_pool_pages_flushed' => ['Innodb_buffer_pool_bytes_dirty'],
        'Innodb_buffer_pool_read_ahead' => ['Innodb_buffer_pool_read_ahead_rnd'],
        'Innodb_truncated_status_writes' => ['Innodb_num_open_files'],
        'Key_blocks_not_flushed' => ['Innodb_available_undo_logs'],
    ];

    /**
     * @var array<string, list<string>> Names registered before the indicated ordinary-sort anchor
     */
    private const MYSQL_5_7_44 = [
        'Com_alter_db' => ['Com_assign_to_keycache'],
        'Com_show_binlog_events' => ['Com_signal'],
        'Com_show_errors' => ['Com_show_events'],
        'Com_shutdown' => ['Com_show_create_user'],
        'Com_stmt_close' => ['Com_group_replication_start', 'Com_group_replication_stop', 'Com_stmt_execute'],
        'Compression' => ['Com_stmt_reprepare'],
        'Innodb_buffer_pool_pages_data' => ['Innodb_buffer_pool_resize_status'],
        'Innodb_buffer_pool_pages_dirty' => ['Innodb_buffer_pool_bytes_data'],
        'Innodb_buffer_pool_pages_flushed' => ['Innodb_buffer_pool_bytes_dirty'],
        'Innodb_buffer_pool_read_ahead' => ['Innodb_buffer_pool_read_ahead_rnd'],
        'Innodb_truncated_status_writes' => ['Innodb_num_open_files'],
        'Key_blocks_not_flushed' => ['Innodb_available_undo_logs'],
    ];

    /**
     * @var array<string, list<string>> Names registered before the indicated ordinary-sort anchor
     */
    private const MYSQL_8_0_44 = [
        'Com_alter_db' => ['Com_assign_to_keycache'],
        'Com_create_trigger' => ['Com_create_resource_group'],
        'Com_dealloc_sql' => ['Com_create_spatial_reference_system'],
        'Com_show_binlog_events' => ['Com_signal'],
        'Com_show_errors' => ['Com_show_events'],
        'Com_show_slave_status' => ['Com_show_replica_status'],
        'Com_shutdown' => ['Com_show_create_user'],
        'Com_slave_start' => ['Com_replica_start'],
        'Com_slave_stop' => ['Com_replica_stop'],
        'Com_stmt_close' => ['Com_group_replication_start', 'Com_group_replication_stop', 'Com_stmt_execute'],
        'Compression' => ['Com_stmt_reprepare'],
        'Innodb_buffer_pool_pages_data' => ['Innodb_buffer_pool_resize_status', 'Innodb_buffer_pool_resize_status_code', 'Innodb_buffer_pool_resize_status_progress'],
        'Innodb_buffer_pool_pages_dirty' => ['Innodb_buffer_pool_bytes_data'],
        'Innodb_buffer_pool_pages_flushed' => ['Innodb_buffer_pool_bytes_dirty'],
        'Innodb_buffer_pool_read_ahead' => ['Innodb_buffer_pool_read_ahead_rnd'],
        'Innodb_log_waits' => ['Innodb_redo_log_read_only', 'Innodb_redo_log_uuid', 'Innodb_redo_log_checkpoint_lsn', 'Innodb_redo_log_current_lsn', 'Innodb_redo_log_flushed_to_disk_lsn', 'Innodb_redo_log_logical_size', 'Innodb_redo_log_physical_size', 'Innodb_redo_log_capacity_resized', 'Innodb_redo_log_resize_status'],
        'Innodb_truncated_status_writes' => ['Innodb_sampled_pages_read', 'Innodb_sampled_pages_skipped', 'Innodb_num_open_files'],
        'Innodb_undo_tablespaces_active' => ['Innodb_undo_tablespaces_total', 'Innodb_undo_tablespaces_implicit', 'Innodb_undo_tablespaces_explicit'],
    ];

    /**
     * @var array<string, list<string>> Names registered before the indicated ordinary-sort anchor
     */
    private const MYSQL_8_2_0 = [
        'Com_alter_db' => ['Com_assign_to_keycache'],
        'Com_create_trigger' => ['Com_create_resource_group'],
        'Com_dealloc_sql' => ['Com_create_spatial_reference_system'],
        'Com_show_binlog_events' => ['Com_signal'],
        'Com_show_errors' => ['Com_show_events'],
        'Com_show_open_tables' => ['Com_show_binary_log_status'],
        'Com_show_slave_status' => ['Com_show_replica_status'],
        'Com_shutdown' => ['Com_show_create_user'],
        'Com_slave_start' => ['Com_replica_start'],
        'Com_slave_stop' => ['Com_replica_stop'],
        'Com_stmt_close' => ['Com_group_replication_start', 'Com_group_replication_stop', 'Com_stmt_execute'],
        'Compression' => ['Com_stmt_reprepare'],
        'Innodb_buffer_pool_pages_data' => ['Innodb_buffer_pool_resize_status', 'Innodb_buffer_pool_resize_status_code', 'Innodb_buffer_pool_resize_status_progress'],
        'Innodb_buffer_pool_pages_dirty' => ['Innodb_buffer_pool_bytes_data'],
        'Innodb_buffer_pool_pages_flushed' => ['Innodb_buffer_pool_bytes_dirty'],
        'Innodb_buffer_pool_read_ahead' => ['Innodb_buffer_pool_read_ahead_rnd'],
        'Innodb_log_waits' => ['Innodb_redo_log_read_only', 'Innodb_redo_log_uuid', 'Innodb_redo_log_checkpoint_lsn', 'Innodb_redo_log_current_lsn', 'Innodb_redo_log_flushed_to_disk_lsn', 'Innodb_redo_log_logical_size', 'Innodb_redo_log_physical_size', 'Innodb_redo_log_capacity_resized', 'Innodb_redo_log_resize_status'],
        'Innodb_truncated_status_writes' => ['Innodb_sampled_pages_read', 'Innodb_sampled_pages_skipped', 'Innodb_num_open_files'],
        'Innodb_undo_tablespaces_active' => ['Innodb_undo_tablespaces_total', 'Innodb_undo_tablespaces_implicit', 'Innodb_undo_tablespaces_explicit'],
    ];

    /**
     * @var array<string, list<string>> Names registered before the indicated ordinary-sort anchor
     */
    private const MYSQL_8_4_7 = [
        'Com_alter_db' => ['Com_assign_to_keycache'],
        'Com_create_trigger' => ['Com_create_resource_group'],
        'Com_dealloc_sql' => ['Com_create_spatial_reference_system'],
        'Com_show_binlog_events' => ['Com_signal'],
        'Com_show_errors' => ['Com_show_events'],
        'Com_show_open_tables' => ['Com_show_binary_log_status'],
        'Com_show_replica_status' => ['Com_show_replicas'],
        'Com_shutdown' => ['Com_show_create_user'],
        'Com_stmt_close' => ['Com_replica_start', 'Com_replica_stop', 'Com_group_replication_start', 'Com_group_replication_stop', 'Com_stmt_execute'],
        'Compression' => ['Com_stmt_reprepare'],
        'Innodb_buffer_pool_pages_data' => ['Innodb_buffer_pool_resize_status', 'Innodb_buffer_pool_resize_status_code', 'Innodb_buffer_pool_resize_status_progress'],
        'Innodb_buffer_pool_pages_dirty' => ['Innodb_buffer_pool_bytes_data'],
        'Innodb_buffer_pool_pages_flushed' => ['Innodb_buffer_pool_bytes_dirty'],
        'Innodb_buffer_pool_read_ahead' => ['Innodb_buffer_pool_read_ahead_rnd'],
        'Innodb_log_waits' => ['Innodb_redo_log_read_only', 'Innodb_redo_log_uuid', 'Innodb_redo_log_checkpoint_lsn', 'Innodb_redo_log_current_lsn', 'Innodb_redo_log_flushed_to_disk_lsn', 'Innodb_redo_log_logical_size', 'Innodb_redo_log_physical_size', 'Innodb_redo_log_capacity_resized', 'Innodb_redo_log_resize_status'],
        'Innodb_truncated_status_writes' => ['Innodb_sampled_pages_read', 'Innodb_sampled_pages_skipped', 'Innodb_num_open_files'],
        'Innodb_undo_tablespaces_active' => ['Innodb_undo_tablespaces_total', 'Innodb_undo_tablespaces_implicit', 'Innodb_undo_tablespaces_explicit'],
    ];

    /**
     * @throws RuntimeException When the release is not MySQL
     * @param list<array{string, string, string, bool}> $entries Alphabetically ordered declarations
     * @return list<array{string, string, string, bool}>
     */
    public static function ordered(array $entries, GrammarRelease $release): array
    {
        $moves = match ($release->value) {
            'mysql-5.6.51' => self::MYSQL_5_6_51,
            'mysql-5.7.44' => self::MYSQL_5_7_44,
            'mysql-8.0.44' => self::MYSQL_8_0_44,
            'mysql-8.1.0' => self::MYSQL_8_0_44,
            'mysql-8.2.0' => self::MYSQL_8_2_0,
            'mysql-8.3.0' => self::MYSQL_8_2_0,
            'mysql-8.4.7' => self::MYSQL_8_4_7,
            'mysql-9.0.1' => self::MYSQL_8_4_7,
            'mysql-9.1.0' => self::MYSQL_8_4_7,
            'pg-16.6', 'pg-17.2', 'sqlite-3.47.2' => throw new RuntimeException('A MySQL release is required.'),
        };
        $moved = array_fill_keys(array_merge(...array_values($moves)), true);
        $byName = array_column($entries, null, 0);
        $rows = [];
        foreach ($entries as $entry) {
            if (!isset($moved[$entry[0]])) {
                foreach ($moves[$entry[0]] ?? [] as $name) {
                    $rows[] = $byName[$name];
                }
                $rows[] = $entry;
            }
        }
        return $rows;
    }
}
