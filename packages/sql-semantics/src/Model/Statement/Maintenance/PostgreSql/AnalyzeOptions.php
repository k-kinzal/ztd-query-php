<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

/**
 * The options ANALYZE accepts: progress reporting, skipping locked relations and the buffer ring size.
 * @visibility public
 * @example Reading the options
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ANALYZE (VERBOSE, SKIP_LOCKED false)');
 *     [$statement->options->verbose, $statement->options->skipLocked] // => [true, false]
 */
final class AnalyzeOptions
{
    /**
     * @param bool $verbose Whether progress messages are reported
     * @param bool $skipLocked Whether relations that cannot be locked immediately are skipped
     * @param BufferUsageLimit|null $bufferUsageLimit Ring buffer size; null uses vacuum_buffer_usage_limit
     */
    public function __construct(
        public readonly bool $verbose = false,
        public readonly bool $skipLocked = false,
        public readonly ?BufferUsageLimit $bufferUsageLimit = null,
    ) {
    }
}
