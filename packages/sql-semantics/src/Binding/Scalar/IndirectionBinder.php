<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Separates qualified column names from array subscripts and record field access.
 *
 * @visibility SqlSemantics
 */
final class IndirectionBinder
{
    /**
     * Resolves the name before evaluating its subscripts in the same lexical scope.
     */
    public function column(Node $node, Scope $scope): Expression
    {
        $name = Tree::child($node, ['ColId']);
        $parts = $scope->identifiers->parts($name ?? $node);
        $elements = Tree::outer($node, ['indirection_el']);
        $tail = [];
        foreach ($elements as $element) {
            $attribute = Tree::child($element, ['attr_name']);
            if ($tail === [] && $attribute !== null) {
                array_push($parts, ...$scope->identifiers->parts($attribute));
            } else {
                $tail[] = $element;
            }
        }
        $value = $scope->column($parts, $node);
        foreach ($tail as $element) {
            $value = $this->apply($value, $element, $scope);
        }
        return $value;
    }

    /**
     * Retains each indexing operand and distinguishes slicing from scalar indexing.
     */
    public function apply(Expression $base, Node $element, Scope $scope): Expression
    {
        $attribute = Tree::child($element, ['attr_name']);
        if ($attribute !== null) {
            return new Expression(ExpressionKind::Operator, new TypeDescriptor($scope->identifiers->dialect, 'unknown'), Nullability::Unknown, $element, [$base], symbol: '.' . $scope->identifiers->parts($attribute)[0]);
        }
        $indices = array_map(static fn (Node $node): Expression => (new ExpressionBinder())->bind($node, $scope), Tree::outer($element, ['a_expr']));
        $slice = in_array(':', array_map(Tree::text(...), $element->children), true);
        $type = $slice ? $base->type->name : (str_ends_with($base->type->name, '[]') ? substr($base->type->name, 0, -2) : 'unknown');
        return new Expression(ExpressionKind::Operator, new TypeDescriptor($scope->identifiers->dialect, $type), Nullability::MaybeNull, $element, [$base, ...$indices], symbol: $slice ? '[:]' : '[]');
    }

    /**
     * Applies record or array indirection to a parenthesized expression.
     */
    public function postfix(Node $node, Node $base, Scope $scope): Expression
    {
        $value = (new ExpressionBinder())->bind($base, $scope);
        $indirection = Tree::child($node, ['opt_indirection']);
        foreach ($indirection === null ? [] : Tree::outer($indirection, ['indirection_el']) as $element) {
            $value = $this->apply($value, $element, $scope);
        }
        return $value;
    }
}
