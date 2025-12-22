<?php

declare(strict_types=1);

namespace ZtdQuery\Schema;

/**
 * Structured column type representation.
 *
 * Combines a platform-independent type family with the platform-specific
 * native type string. Immutable value object.
 */
final class ColumnType
{
    /**
     * @param ColumnTypeFamily $family The abstract type family.
     * @param string $nativeType Platform-specific type string (e.g. "SIGNED", "INTEGER", "int4").
     */
    public function __construct(
        public readonly ColumnTypeFamily $family,
        public readonly string $nativeType,
    ) {
    }
}
