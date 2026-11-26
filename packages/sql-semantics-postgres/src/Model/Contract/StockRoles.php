<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Model\Contract;

/**
 * Decoded role names that initdb installs under fixed cluster identities.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class StockRoles
{
    /**
     * Checks an identity in the fixed system catalog for this release.
     */
    public static function contains(GrammarRelease $release, string $name): bool
    {
        return match ($name) {
            'pg_checkpoint',
            'pg_create_subscription',
            'pg_database_owner',
            'pg_execute_server_program',
            'pg_monitor',
            'pg_read_all_data',
            'pg_read_all_settings',
            'pg_read_all_stats',
            'pg_read_server_files',
            'pg_signal_backend',
            'pg_stat_scan_tables',
            'pg_use_reserved_connections',
            'pg_write_all_data',
            'pg_write_server_files' => true,
            'pg_maintain' => $release === GrammarRelease::PostgreSql172,
            default => false,
        };
    }
}
