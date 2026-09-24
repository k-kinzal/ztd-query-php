<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Catalog;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\ObjectAddress;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * An operator selected by its symbol and its left and right operand types; a unary operator omits one side.
 * @visibility public
 * @example Addressing a prefix operator
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind("COMMENT ON OPERATOR app.- (NONE, integer) IS 'negate'");
 *     $statement->object instanceof \SqlSemantics\Model\Definition\Catalog\OperatorIdentity // => true
 *     $statement->object->left // => null
 *     $statement->object->right->name // => 'integer'
 * @example Rejecting an operator without operand types
 *     new \SqlSemantics\Model\Definition\Catalog\OperatorIdentity(new \SqlSemantics\Model\Relation\QualifiedName(['+']), null, null); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class OperatorIdentity implements ObjectAddress
{
    /**
     * A binary operator declares both operand types; a unary operator declares exactly one.
     * @throws InvalidStructure
     */
    public function __construct(public readonly QualifiedName $name, public readonly ?TypeDescriptor $left, public readonly ?TypeDescriptor $right)
    {
        CatalogInvariant::name($name, 2);
        if ($left === null && $right === null) {
            throw new InvalidStructure('An operator address requires at least one operand type.');
        }
        foreach ([$left, $right] as $type) {
            if ($type !== null && $type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('Operator operand types require the PostgreSQL dialect.');
            }
        }
    }
}
