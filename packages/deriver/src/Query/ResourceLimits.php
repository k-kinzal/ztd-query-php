<?php

declare(strict_types=1);

namespace Deriver\Query;

use Deriver\Exception\InvalidInputException;

/**
 * Runtime protections independent of reproducible structural query budgets.
 *
 * Memory is additional allocated PHP memory since the query starts; source capture and
 * indexing precede this limit. A zero duration disables the optional wall-clock limit.
 * Resource interruptions are explicit frontiers and are not stored in the result cache.
 *
 * @visibility public
 * @example Limiting additional query memory
 *     (new \Deriver\Query\ResourceLimits(memoryBytes: 16777216))->memoryBytes // => 16777216
 */
final class ResourceLimits
{
    /**
     * @param int $memoryBytes Maximum additional PHP allocation, at least one MiB
     * @param float $seconds Maximum query duration, or zero for no wall-clock limit
     * @param CancellationToken|null $cancellation Optional caller-controlled cancellation
     * @param int $stackFrames Maximum host call frames, independently of semantic call-site history
     * @throws InvalidInputException If a memory or duration limit is invalid
     */
    public function __construct(public readonly int $memoryBytes = 268435456, public readonly float $seconds = 0.0, public readonly ?CancellationToken $cancellation = null, public readonly int $stackFrames = 2048)
    {
        if ($memoryBytes < 1048576 || !is_finite($seconds) || $seconds < 0.0 || $stackFrames < 64) {
            throw new InvalidInputException('Resource limits require at least one MiB of memory, 64 stack frames, and a finite nonnegative duration.');
        }
    }
}
