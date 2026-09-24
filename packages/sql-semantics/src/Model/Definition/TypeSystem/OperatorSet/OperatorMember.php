<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\TypeSystem\OperatorSet;

use SqlSemantics\Model\Definition\TypeSystem\Definition\DefinitionKind;
use SqlSemantics\Model\Definition\TypeSystem\TypeSystemInvariant;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Type\TypeDescriptor;

/**
 * An operator at a strategy number, with its operand types when they differ from the indexed type, for search or for ordering by an operator family.
 * @visibility public
 * @example Reading an ordering operator
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build()))->bind('CREATE OPERATOR CLASS point_ops FOR TYPE point USING gist AS OPERATOR 15 <-> (point, point) FOR ORDER BY float_ops');
 *     $statement->members[0]->strategy // => 15
 *     $statement->members[0]->orderFamily->parts // => ['float_ops']
 * @example Rejecting strategy number zero
 *     new \SqlSemantics\Model\Definition\TypeSystem\OperatorSet\OperatorMember(0, new \SqlSemantics\Model\Relation\QualifiedName(['<'])); // throws \SqlSemantics\Model\Validation\InvalidStructure
 */
final class OperatorMember implements OperatorSetMember
{
    /**
     * @throws InvalidStructure
     */
    public function __construct(public readonly int $strategy, public readonly QualifiedName $operator, public readonly ?TypeDescriptor $left = null, public readonly ?TypeDescriptor $right = null, public readonly ?QualifiedName $orderFamily = null)
    {
        MemberNumber::validate($strategy);
        MemberNumber::types($left, $right);
        TypeSystemInvariant::name($operator);
        if (preg_match(DefinitionKind::SYMBOL, $operator->parts[count($operator->parts) - 1]) !== 1) {
            throw new InvalidStructure('An operator member names an operator symbol.');
        }
        if ($orderFamily !== null) {
            TypeSystemInvariant::name($orderFamily);
        }
    }
}
