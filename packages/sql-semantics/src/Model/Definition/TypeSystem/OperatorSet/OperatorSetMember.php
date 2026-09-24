<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\OperatorSet;

/**
 * A member of an operator class or operator family: an operator, a support function, or the storage type.
 * @visibility public
 * @example Reading the members of an operator class
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS int_ops FOR TYPE integer USING btree AS OPERATOR 1 <, FUNCTION 1 btint4cmp(integer, integer)');
 *     $statement->members[0] instanceof \SqlSemantics\Model\Definition\TypeSystem\OperatorSet\OperatorSetMember // => true
 */
interface OperatorSetMember
{
}
