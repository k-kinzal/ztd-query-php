<?php

declare(strict_types=1);

namespace Deriver\Internal\IR;

/**
 * Normalized signature information used by source and model calls.
 *
 * @visibility root
 */
final class Parameter
{
    /**
     * @param string $name name
     * @param string $type type
     * @param bool $byReference byReference
     * @param bool $variadic variadic
     * @param CallableIR|null $default default
     * @param int $promotion promotion
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type = 'mixed',
        public readonly bool $byReference = false,
        public readonly bool $variadic = false,
        public readonly ?CallableIR $default = null,
        public readonly int $promotion = 0,
    ) {
    }
}
