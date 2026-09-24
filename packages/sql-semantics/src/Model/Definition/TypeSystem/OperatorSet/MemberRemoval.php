<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\OperatorSet;

use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * An operator family member to remove, addressed by kind, number, and left and right associated types; one written type stands for both.
 * @visibility public
 * @example Reading a removed operator
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY integer_ops USING btree DROP OPERATOR 1 (integer)');
 *     $statement->members[0]->right->name // => 'integer'
 *     $statement->toString() // => 'ALTER OPERATOR FAMILY "integer_ops" USING "btree" DROP OPERATOR 1(integer, integer)'
 */
final class MemberRemoval
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly MemberKind $kind, public readonly int $number, public readonly TypeDescriptor $left, public readonly TypeDescriptor $right)
    {
        MemberNumber::validate($number);
        MemberNumber::types($left, $right);
    }
}
