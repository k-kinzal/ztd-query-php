<?php

declare(strict_types=1);

namespace SqlSemantics\Type;

use SqlSemantics\Dialect;

/**
 * A database type identity, preserving declared modifiers and SQLite affinity.
 *
 * @example Reading semantic facts
 *     $type = new \SqlSemantics\Type\TypeDescriptor(\SqlSemantics\Dialect::PostgreSql, 'numeric', ['10', '2']);
 *     $type->modifiers // => ['10', '2']
 *
 * @visibility public
 */
final class TypeDescriptor
{
    /**
     * @param Dialect $dialect Database language
     * @param string $name Canonical database type, or unknown for unresolved input
     * @param list<string> $modifiers Precision, scale, length, or other declared modifiers
     * @param string|null $affinity SQLite storage affinity; not a runtime storage-class guarantee
     */
    public function __construct(
        public readonly Dialect $dialect,
        public readonly string $name,
        public readonly array $modifiers = [],
        public readonly ?string $affinity = null,
    ) {
    }
}
