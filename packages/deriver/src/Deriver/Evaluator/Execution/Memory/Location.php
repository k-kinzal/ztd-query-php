<?php

declare(strict_types=1);

namespace Deriver\Deriver\Evaluator\Execution\Memory;

/**
 * An address into one storage root, distinct from an object identity.
 *
 * @visibility Deriver\Deriver\Evaluator
 * @visibility Deriver\Deriver\Evaluator\Execution
 */
final class Location
{
    /**
     * @param string $root root
     * @param list<int|string> $path path
     * @param string $local local
     * @param bool $unknown unknown
     */
    public function __construct(
        public readonly string $root,
        public readonly array $path = [],
        public readonly string $local = '',
        public readonly bool $unknown = false,
    ) {
    }
}
