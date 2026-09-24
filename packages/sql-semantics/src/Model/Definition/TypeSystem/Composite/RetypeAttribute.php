<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\Composite;

use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Changes the declared type and optionally the collation of a composite type attribute.
 * @visibility public
 * @example Changing an attribute type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER TYPE pair ALTER ATTRIBUTE amount SET DATA TYPE bigint');
 *     $statement->changes[0]->name // => 'amount'
 *     $statement->changes[0]->type->name // => 'bigint'
 */
final class RetypeAttribute implements AttributeChange
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly string $name, public readonly TypeDescriptor $type, public readonly ?QualifiedName $collation = null, public readonly DropBehavior $behavior = DropBehavior::Default)
    {
        TypeSystemInvariant::identifier($name);
        TypeSystemInvariant::type($type);
        if ($collation !== null) {
            TypeSystemInvariant::name($collation);
        }
    }
}
