<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Declaration;

use InvalidArgumentException;
use SqlSemantics\Statement\Element;

/**
 * A declared column type and the column facts the declaration itself implies.
 *
 * Some type spellings are shorthand for a type together with column
 * properties, such as a serial type standing for an integer that is generated
 * automatically and never NULL. The type keeps only type facts; the implied
 * column facts are reported here for the column declaration to apply.
 *
 * @example Accept this semantic value in a database-independent consumer
 *     $consume = static fn (\SqlSemantics\Statement\Declaration\TypeDeclaration $value): bool => $value->autoIncrement;
 *     $consume instanceof \Closure // => true
 *
 * @visibility public
 */
final class TypeDeclaration
{
    /**
     * @param TypeDescriptor $type Declared type facts
     * @param bool $autoIncrement Whether the database generates the value of an omitted column
     * @param bool $notNull Whether the declaration excludes NULL without a NOT NULL attribute
     * @param bool $unique Whether the declaration implies a unique key on the column
     * @param Element|null $source Complete typed syntax when read as a standalone type
     * @throws InvalidArgumentException When the supplied syntax is mutable
     */
    public function __construct(
        public readonly TypeDescriptor $type,
        public readonly bool $autoIncrement = false,
        public readonly bool $notNull = false,
        public readonly bool $unique = false,
        public readonly ?Element $source = null,
    ) {
        Invariant::elements($source);
    }
}
