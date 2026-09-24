<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Maintenance\PostgreSql;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * The BUFFER_USAGE_LIMIT of VACUUM or ANALYZE: a memory quantity in kilobytes, zero for no ring buffer, or from 128 kB to 16 GB.
 * @visibility public
 * @example Reading the requested size
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("ANALYZE (BUFFER_USAGE_LIMIT '2MB')");
 *     [$statement->options->bufferUsageLimit->setting, $statement->options->bufferUsageLimit->kilobytes] // => ['2MB', 2048]
 * @example Rejecting a size below the ring-buffer minimum
 *     new \SqlSemantics\Model\Statement\Maintenance\PostgreSql\BufferUsageLimit('64kB'); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class BufferUsageLimit
{
    /**
     * The quantity in kilobytes, read with the server's integer parameter rules.
     */
    public readonly int $kilobytes;

    /**
     * @param string $setting Quantity as written: a number with an optional B, kB, MB, GB or TB unit
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $setting)
    {
        $kilobytes = MemoryQuantity::kilobytes($setting);
        if ($kilobytes === null || ($kilobytes !== 0 && ($kilobytes < 128 || $kilobytes > 16777216))) {
            throw new InvalidStructure('BUFFER_USAGE_LIMIT requires 0 or a size from 128 kB to 16 GB.');
        }
        $this->kilobytes = $kilobytes;
    }
}
