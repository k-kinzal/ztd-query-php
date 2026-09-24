<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The options of VACUUM with their server defaults; null marks a setting left to the table or server configuration.
 * @visibility public
 * @example Reading legacy keyword options
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('VACUUM FULL FREEZE');
 *     [$statement->options->full, $statement->options->freeze, $statement->options->analyze] // => [true, true, false]
 * @example Rejecting a parallel full vacuum
 *     new \SqlSemantics\Model\Statement\Maintenance\PostgreSql\VacuumOptions(full: true, parallel: 2); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class VacuumOptions
{
    /**
     * @param bool $verbose Whether progress messages are reported
     * @param bool $skipLocked Whether relations that cannot be locked immediately are skipped
     * @param BufferUsageLimit|null $bufferUsageLimit Ring buffer size; null uses vacuum_buffer_usage_limit
     * @param bool $analyze Whether statistics are also collected
     * @param bool $freeze Whether tuples are aggressively frozen
     * @param bool $full Whether tables are rewritten
     * @param bool $disablePageSkipping Whether the visibility map is ignored
     * @param IndexCleanup|null $indexCleanup Index cleanup request; null uses the table setting
     * @param bool $processMain Whether the main relation is processed
     * @param bool $processToast Whether the TOAST table is processed
     * @param bool|null $truncate Whether empty trailing pages are truncated; null uses the table setting
     * @param int|null $parallel Parallel index workers from 0 to 1024, where 0 disables parallelism; null lets the server choose
     * @param bool $skipDatabaseStats Whether updating database-wide statistics is skipped
     * @param bool $onlyDatabaseStats Whether only database-wide statistics are updated
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly bool $verbose = false,
        public readonly bool $skipLocked = false,
        public readonly ?BufferUsageLimit $bufferUsageLimit = null,
        public readonly bool $analyze = false,
        public readonly bool $freeze = false,
        public readonly bool $full = false,
        public readonly bool $disablePageSkipping = false,
        public readonly ?IndexCleanup $indexCleanup = null,
        public readonly bool $processMain = true,
        public readonly bool $processToast = true,
        public readonly ?bool $truncate = null,
        public readonly ?int $parallel = null,
        public readonly bool $skipDatabaseStats = false,
        public readonly bool $onlyDatabaseStats = false,
    ) {
        if ($parallel !== null && ($parallel < 0 || $parallel > 1024)) {
            throw new InvalidStructure('PARALLEL requires a worker count from 0 to 1024.');
        }
        if ($full && (($parallel ?? 0) > 0 || $bufferUsageLimit !== null || $disablePageSkipping || !$processToast)) {
            throw new InvalidStructure('VACUUM FULL cannot run in parallel, limit its buffer usage, disable page skipping or skip the TOAST table.');
        }
        if ($onlyDatabaseStats && ($skipLocked || $analyze || $freeze || $full || $disablePageSkipping || $skipDatabaseStats)) {
            throw new InvalidStructure('ONLY_DATABASE_STATS cannot be combined with other VACUUM options.');
        }
    }
}
