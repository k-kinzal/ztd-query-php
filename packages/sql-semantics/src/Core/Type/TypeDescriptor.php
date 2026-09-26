<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Type;

use SqlSemantics\Core\Dialect;

/**
 * A database type identity, preserving declared modifiers and storage affinity.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Core\Type\TypeDescriptor $value): string => $value::class;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class TypeDescriptor
{
    /**
     * @param Dialect $dialect Database language
     * @param string $name Canonical database type, or unknown for unresolved input
     * @param list<string> $modifiers Precision, scale, length, or other declared modifiers
     * @param string|null $affinity Storage affinity; not a runtime storage-class guarantee
     */
    public function __construct(
        public readonly Dialect $dialect,
        public readonly string $name,
        public readonly array $modifiers = [],
        public readonly ?string $affinity = null,
    ) {
    }
}
