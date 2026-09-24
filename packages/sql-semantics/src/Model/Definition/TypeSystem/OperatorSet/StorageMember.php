<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\OperatorSet;

use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * The type an operator class stores in the index when it differs from the indexed type.
 * @visibility public
 * @example Reading the storage type
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS box_ops FOR TYPE polygon USING gist AS STORAGE box');
 *     $statement->members[0]->type->name // => 'box'
 */
final class StorageMember implements OperatorSetMember
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly TypeDescriptor $type)
    {
        TypeSystemInvariant::type($type);
    }
}
