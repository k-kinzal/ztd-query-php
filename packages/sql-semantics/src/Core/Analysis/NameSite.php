<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Analysis;

use SqlSemantics\Statement\Element;
use SqlSemantics\Statement\ReferenceKind;

/**
 * A place in a statement that writes a table name, before the name is resolved.
 *
 * @visibility SqlSemantics
 */
final class NameSite
{
    /**
     * @param Element $value The value that writes the name
     * @param non-empty-list<string> $name The decoded parts of the name, in writing order
     * @param ReferenceKind $kind How the site uses the name: Declaration declares it, Drop drops it, CommonTableExpression defines it, and Dependency merely refers to it
     * @param bool $conditional Whether the declaration or drop is conditional, as with IF EXISTS
     */
    public function __construct(
        public readonly Element $value,
        public readonly array $name,
        public readonly ReferenceKind $kind,
        public readonly bool $conditional = false,
    ) {
    }
}
