<?php

declare(strict_types=1);

namespace SqlFixture\Schema;

/**
 * The size and numeric precision carried by a parsed SQL type declaration.
 *
 * @visibility root
 */
final class TypeShape
{
    /**
     * Records the normalized type while preserving an absent length or scale.
     */
    public function __construct(
        public readonly string $type,
        public readonly ?int $length = null,
        public readonly ?int $precision = null,
        public readonly ?int $scale = null,
        public readonly bool $autoIncrement = false,
    ) {
    }
}
