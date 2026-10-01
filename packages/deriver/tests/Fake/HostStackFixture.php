<?php

declare(strict_types=1);

namespace Tests\Fake;

use Deriver\Evaluation\Control\Resources;

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
}
