<?php

declare(strict_types=1);

namespace Deriver\Result;

/**
 * Independent measures of completeness, precision, and correlation.
 *
 * @visibility public
 * @example Inspecting the contract
 *     (new \Deriver\Result\Assessment())->closure // => 'closed'
 */
final class Assessment
{
    /**
     * @param string $closure closure
     * @param string $precision precision
     * @param string $correlation correlation
     * @param string $coverage coverage
     * @param string $enumeration enumeration
     */
    public function __construct(
        public readonly string $closure = 'closed',
        public readonly string $precision = 'exact-symbolic',
        public readonly string $correlation = 'preserved',
        public readonly string $coverage = 'over-approximation',
        public readonly string $enumeration = 'not-enumerated',
    ) {
    }
}
