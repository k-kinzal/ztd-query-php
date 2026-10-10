<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Variable\Catalog;

use SqlSemantics\Contract\GrammarRelease;

/**
 * The system variables MySQL 5.6 and 5.7 deprecate, which the server warns about each time a statement reads or assigns one.
 *
 * Each variable maps to the text the warning ends with: the variable to use instead, or nothing.
 * The warning names the variable without its scope (verified on live 5.6.51 and 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/5.7/en/added-deprecated-removed.html,
 * https://dev.mysql.com/doc/refman/5.6/en/server-system-variables.html.
 *
 * @visibility public
 * @example The warning of tx_isolation in MySQL 5.7
 *     (new \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\DeprecatedVariables())->warning('TX_ISOLATION', \SqlSemantics\Contract\GrammarRelease::MySql5744) // => "'@@tx_isolation' is deprecated and will be removed in a future release. Please use '@@transaction_isolation' instead"
 */
final class DeprecatedVariables
{
    /**
     * The variables MySQL 5.6 deprecates, with the end of the warning of each.
     */
    public const MYSQL_56 = [
        'avoid_temporal_upgrade' => '', 'binlogging_impossible_mode' => " Please use '@@binlog_error_action' instead", 'date_format' => '',
        'datetime_format' => '', 'delayed_insert_limit' => '', 'delayed_insert_timeout' => '', 'delayed_queue_size' => '', 'have_profiling' => '',
        'max_delayed_threads' => '', 'max_insert_delayed_threads' => '', 'max_tmp_tables' => '', 'multi_range_count' => '', 'profiling' => '',
        'profiling_history_size' => '', 'show_old_temporals' => '', 'simplified_binlog_gtid_recovery' => " Please use '@@binlog_gtid_simple_recovery' instead",
        'storage_engine' => " Please use '@@default_storage_engine' instead", 'thread_concurrency' => '', 'time_format' => '', 'timed_mutexes' => '',
    ];

    /**
     * The variables MySQL 5.7 deprecates, with the end of the warning of each.
     */
    public const MYSQL_57 = [
        'avoid_temporal_upgrade' => '', 'binlog_max_flush_queue_time' => '', 'date_format' => '', 'datetime_format' => '', 'delayed_insert_limit' => '',
        'delayed_insert_timeout' => '', 'delayed_queue_size' => '', 'have_profiling' => '', 'have_query_cache' => '',
        'log_warnings' => ' Please use log_error_verbosity instead', 'max_delayed_threads' => '', 'max_insert_delayed_threads' => '', 'max_tmp_tables' => '',
        'metadata_locks_cache_size' => '', 'metadata_locks_hash_instances' => '', 'multi_range_count' => '', 'profiling' => '', 'profiling_history_size' => '',
        'query_cache_limit' => '', 'query_cache_min_res_unit' => '', 'query_cache_size' => '', 'query_cache_type' => '', 'query_cache_wlock_invalidate' => '',
        'show_old_temporals' => '', 'sync_frm' => '', 'time_format' => '', 'tx_isolation' => " Please use '@@transaction_isolation' instead",
        'tx_read_only' => " Please use '@@transaction_read_only' instead",
    ];

    /**
     * Answers the warning a release raises for a variable, named in any case, or null when the release does not deprecate it.
     */
    public function warning(string $name, GrammarRelease $release): ?string
    {
        $variables = $release === GrammarRelease::MySql5651 ? self::MYSQL_56 : ($release === GrammarRelease::MySql5744 ? self::MYSQL_57 : []);
        $variable = strtolower($name);
        if (!isset($variables[$variable])) {
            return null;
        }

        return "'@@" . $variable . "' is deprecated and will be removed in a future release." . $variables[$variable];
    }
}
