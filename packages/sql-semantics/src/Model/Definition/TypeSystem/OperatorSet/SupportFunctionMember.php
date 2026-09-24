<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\OperatorSet;

use SqlSemantics\Model\Definition\Routine\RoutineByName;
use SqlSemantics\Model\Definition\Routine\RoutineBySignature;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * A support function at a support number, with the associated operand types when they are given; one given type stands for both.
 * @visibility public
 * @example Reading a support function with associated types
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('ALTER OPERATOR FAMILY integer_ops USING btree ADD FUNCTION 1 (integer) btint48cmp(integer, bigint)');
 *     $statement->members[0]->right->name // => 'integer'
 *     $statement->members[0]->function->name->parts // => ['btint48cmp']
 */
final class SupportFunctionMember implements OperatorSetMember
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $number, public readonly RoutineByName|RoutineBySignature $function, public readonly ?TypeDescriptor $left = null, public readonly ?TypeDescriptor $right = null)
    {
        MemberNumber::validate($number);
        MemberNumber::types($left, $right);
    }
}
