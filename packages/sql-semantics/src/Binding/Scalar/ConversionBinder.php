<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Type\Nullability;

/**
 * Binds explicit type conversions and collation selection as distinct operators.
 * @visibility SqlSemantics
 */
final class ConversionBinder
{
    /**
     * Resolves a collation name while retaining its value operand.
     */
    public static function collation(Node $node, Scope $scope): ?Expression
    {
        $children = Tree::significant($node);
        if (count($children) === 3 && strtoupper(Tree::text($children[1])) === 'COLLATE') {
            $operand = (new ExpressionBinder())->bind($children[0], $scope);
            $parts = $children[2] instanceof Node ? $scope->identifiers->parts($children[2]) : [$scope->identifiers->name($children[2])];
            return new \SqlSemantics\Model\Scalar\Operator\CollatedExpression($operand->facts, $node, $operand, new \SqlSemantics\Model\Relation\QualifiedName($parts));
        }
        return null;
    }

    /**
     * @param list<Expression> $operands Cast operand bound in the caller's scope
     */
    public static function cast(Node $node, Scope $scope, array $operands): ?Expression
    {
        $text = strtoupper(Tree::text($node));
        $typeNode = Tree::child($node, ['Typename', 'cast_type', 'typetoken']);
        if ($typeNode === null && $scope->identifiers->dialect === \SqlSemantics\Dialect::Sqlite) {
            $typeNode = array_values(array_filter($node->children, static fn ($child): bool => $child instanceof Node && $child->name === 'typetoken'))[0] ?? null;
        }
        if ($typeNode !== null && (str_starts_with($text, 'CAST ') || str_contains($text, ' :: '))) {
            $type = (new TypeReader($scope->identifiers->dialect))->read($typeNode);
            return new \SqlSemantics\Model\Scalar\Operator\CastExpression(new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $operands[0]->nullability ?? Nullability::Unknown, []), $node, ($operands)[0], \SqlSemantics\Model\Scalar\Operator\CastMode::Explicit);
        }
        return null;
    }
}
