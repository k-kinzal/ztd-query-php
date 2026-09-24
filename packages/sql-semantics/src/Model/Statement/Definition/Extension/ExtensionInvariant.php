<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Extension;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog as Address;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Shared rules of PostgreSQL extension, language, and access method definitions.
 * @visibility SqlSemantics
 */
final class ExtensionInvariant
{
    /**
     * Extensions, procedural languages, and access methods are PostgreSQL catalog objects.
     * @throws InvalidStructure
     */
    public static function dialect(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Extension, language, and access method definitions require PostgreSQL.');
        }
    }

    /**
     * Requires a nonempty object name and an optional nonempty setting such as a version or schema.
     * @throws InvalidStructure
     */
    public static function names(string $name, ?string ...$optional): void
    {
        CatalogInvariant::identifier($name);
        foreach ($optional as $value) {
            if ($value !== null) {
                CatalogInvariant::identifier($value);
            }
        }
    }

    /**
     * A handler or validator function is named by up to a database, a schema, and a function name.
     * @throws InvalidStructure
     */
    public static function function(?QualifiedName $name): void
    {
        if ($name !== null) {
            CatalogInvariant::name($name, 3);
        }
    }

    /**
     * An extension member is a whole object: never a column, a relation member, a domain constraint, a large object, or a type addressed by catalog name.
     * @throws InvalidStructure
     */
    public static function member(ObjectAddress $object): void
    {
        if ($object instanceof Address\RelationMemberIdentity || $object instanceof Address\DomainConstraintIdentity || $object instanceof Address\LargeObjectIdentity || $object instanceof Address\TypeNameIdentity) {
            throw new InvalidStructure('An extension member is a whole catalog object the grammar can name.');
        }
    }
}
