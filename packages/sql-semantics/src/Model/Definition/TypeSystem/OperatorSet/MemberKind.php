<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\OperatorSet;

/**
 * Whether a removed operator family member is an operator or a support function.
 * @visibility public
 * @example Reading the kind of a removed member
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY integer_ops USING btree DROP FUNCTION 1 (integer, bigint)');
 *     $statement->members[0]->kind // => \SqlSemantics\Model\Definition\TypeSystem\OperatorSet\MemberKind::Function
 */
enum MemberKind: string
{
    case Operator = 'OPERATOR';
    case Function = 'FUNCTION';
}
