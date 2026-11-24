<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Ir\Maintenance;

/**
 * Built-in SQLite connection flags in the pinned stock build.
 *
 * @visibility public
 * @example Naming the native foreign key flag
 *     \SqlSemantics\Platform\Sqlite\Ir\Maintenance\FlagName::ForeignKeys->value // => 'foreign_keys'
 */
enum FlagName: string
{
    case AutomaticIndex = 'automatic_index';
    case CellSizeCheck = 'cell_size_check';
    case CheckpointFullfsync = 'checkpoint_fullfsync';
    case CountChanges = 'count_changes';
    case DeferForeignKeys = 'defer_foreign_keys';
    case EmptyResultCallbacks = 'empty_result_callbacks';
    case ForeignKeys = 'foreign_keys';
    case FullColumnNames = 'full_column_names';
    case Fullfsync = 'fullfsync';
    case IgnoreCheckConstraints = 'ignore_check_constraints';
    case LegacyAlterTable = 'legacy_alter_table';
    case QueryOnly = 'query_only';
    case ReadUncommitted = 'read_uncommitted';
    case RecursiveTriggers = 'recursive_triggers';
    case ReverseUnorderedSelects = 'reverse_unordered_selects';
    case ShortColumnNames = 'short_column_names';
    case TrustedSchema = 'trusted_schema';
    case WritableSchema = 'writable_schema';
}
