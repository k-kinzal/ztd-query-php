<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Evaluation\Context;
use Deriver\Evaluation\Control\Resources;
use Deriver\Query\ResourceLimits;
use Deriver\Reference\SourceRef;

/**
 * Exercises host stack capacity without executing captured application code.
 * @visibility root
 */
final class HostStackFixture
{
    /**
     * @param Resources $resources Query resource policy
     * @param int $depth Remaining host frames to allocate
     * @param bool $call Whether this operation enters another callable
     * @return string|null Interruption reported at the deepest frame
     */
    public static function descend(Resources $resources, int $depth, bool $call): ?string
    {
        return $depth === 0 ? $resources->reason(call:$call) : self::descend($resources, $depth - 1, $call);
    }

    /**
     * Asks a query context to enter a callable from a deep host stack.
     * @param Context $context Query context
     * @param SourceRef $source Callable entry
     * @param int $depth Remaining host frames to allocate
     * @return bool Whether the context admits the call
     */
    public static function enter(Context $context, SourceRef $source, int $depth): bool
    {
        return $depth === 0 ? $context->available($source, call:true) : self::enter($context, $source, $depth - 1);
    }

    /**
     * Starts a query below caller frames that its stack budget must not count.
     * @param ResourceLimits $limits Query resource policy
     * @param int $depth Caller frames to allocate first
     * @return Resources Resource checks created at that depth
     */
    public static function open(ResourceLimits $limits, int $depth): Resources
    {
        return $depth === 0 ? new Resources($limits) : self::open($limits, $depth - 1);
    }
}
