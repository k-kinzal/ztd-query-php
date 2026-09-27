<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use InvalidArgumentException;

/**
 * A database type identity, preserving declared modifiers and storage affinity.
 *
 * @example Describing a declared type
 *     $type = new \SqlSemantics\Statement\Declaration\TypeDescriptor('decimal', ['10', '2']);
 *     $type->modifiers // => ['10', '2']
 *
 * @visibility public
 */
final class TypeDescriptor
{
    /**
     * @param string $name Canonical database type, or unknown for unresolved input
     * @param list<string> $modifiers Precision, scale, length, or other declared modifiers
     * @param string|null $affinity Storage affinity; not a runtime storage-class guarantee
     * @throws InvalidArgumentException When type modifiers are not an ordered list of strings
     */
    public function __construct(
        public readonly string $name,
        public readonly array $modifiers = [],
        public readonly ?string $affinity = null,
    ) {
        Invariant::names($modifiers);
    }
}
