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
 * Time and memory are checked cooperatively between evaluator operations and during joins;
 * an individual parser, model, or value operation cannot be preempted by these limits.
 * Memory, time, and cancellation end the whole query; the stack frame limit only seals the
 * call it refuses as a residual with a STACK_LIMIT frontier, and the rest of the query continues.
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
     * @param int $stackFrames Maximum host call frames the query adds to its caller's stack, independently of semantic call-site history; an active Xdebug nesting limit can lower it
     * @throws InvalidInputException If a memory or duration limit is invalid
     */
    public function __construct(public readonly int $memoryBytes = 268435456, public readonly float $seconds = 0.0, public readonly ?CancellationToken $cancellation = null, public readonly int $stackFrames = 2048)
    {
        if ($memoryBytes < 1048576 || !is_finite($seconds) || $seconds < 0.0 || $stackFrames < 64) {
            throw new InvalidInputException('Resource limits require at least one MiB of memory, 64 stack frames, and a finite nonnegative duration.');
        }
    }
}
