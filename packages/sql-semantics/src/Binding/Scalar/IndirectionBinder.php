<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
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
        $prefix = [$name ?? $node];
        foreach ($elements as $element) {
            $attribute = Tree::child($element, ['attr_name']);
            if ($tail === [] && $attribute !== null) {
                array_push($parts, ...$scope->identifiers->parts($attribute));
                $prefix[] = $element;
            } else {
                $tail[] = $element;
            }
        }
        $value = $scope->column($parts, $tail === [] ? $node : new Node('column_reference', 0, $prefix));
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
        $source = new Node('indirection', 0, [$base->source, $element]);
        $attribute = Tree::child($element, ['attr_name']);
        if ($attribute !== null) {
            return new \SqlSemantics\Model\Scalar\Reference\FieldAccess(new \SqlSemantics\Model\Scalar\ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'unknown'), Nullability::Unknown), $source, $base, $scope->identifiers->parts($attribute)[0]);
        }
        $bounds = [];
        $position = 0;
        $slice = false;
        foreach ($element->children as $child) {
            if (Tree::text($child) === ':') {
                $position = 1;
                $slice = true;
            } elseif ($child instanceof Node && in_array($child->name, ['a_expr', 'opt_slice_bound'], true) && Tree::hasTokens($child)) {
                $bounds[$position] = (new ExpressionBinder())->bind($child, $scope);
            }
        }
        $type = $slice ? $base->type : ($base->type->identity instanceof \SqlSemantics\Type\Identity\ArrayStorage ? $base->type->identity->element : TypeDescriptor::builtin($scope->identifiers->dialect, 'unknown'));
        $facts = new \SqlSemantics\Model\Scalar\ExpressionFacts($type, Nullability::MaybeNull);
        if ($slice) {
            return new \SqlSemantics\Model\Scalar\Reference\SliceAccess($facts, $source, $base, $bounds[0] ?? null, $bounds[1] ?? null);
        }
        if (!isset($bounds[0])) {
            Tree::invalid($element, 'array index');
        }
        return new \SqlSemantics\Model\Scalar\Reference\ElementAccess($facts, $source, $base, $bounds[0]);
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
