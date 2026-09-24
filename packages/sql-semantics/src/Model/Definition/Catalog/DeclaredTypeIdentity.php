<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A type or domain addressed by a full PostgreSQL type declaration, including array forms.
 * Comments, security labels, and removal address types this way.
 * @visibility public
 * @example Addressing an array type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COMMENT ON TYPE integer[] IS 'ids'");
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\DeclaredTypeIdentity // => true
 *     $statement->object->type->name // => 'integer[]'
 * @example Rejecting a type from another database language
 *     new \SqlSemantics\Model\Definition\Catalog\DeclaredTypeIdentity(\SqlSemantics\Model\Definition\Catalog\Kind\TypeKind::Type, \SqlSemantics\Type\TypeDescriptor::builtin(\SqlSemantics\Dialect::MySql, 'integer')); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class DeclaredTypeIdentity implements ObjectAddress
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly Kind\TypeKind $kind, public readonly TypeDescriptor $type)
    {
        if ($type->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('A catalog type address requires a PostgreSQL type declaration.');
        }
    }
}
