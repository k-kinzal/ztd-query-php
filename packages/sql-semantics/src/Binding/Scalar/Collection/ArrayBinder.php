<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar\Collection;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\TypeResolution;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Scalar\Conditional\ArrayComparison;
use SqlSemantics\Model\Scalar\Query\ArraySubquery;
use SqlSemantics\Model\Scalar\Query\Quantifier;
use SqlSemantics\Model\Scalar\Value\ArrayConstructor;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\Model\Validation\RowShape;

/**
 * Binds PostgreSQL ARRAY[...] constructors and ARRAY(subquery) with their element type.
 * @visibility SqlSemantics
 */
final class ArrayBinder
{
    /**
     * Recognizes the ARRAY keyword followed by a bracketed element list or a parenthesized query.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function bind(Node $source, Scope $scope): ?Expression
    {
        $first = $source->children[0] ?? null;
        if ($scope->identifiers->dialect !== Dialect::PostgreSql || !$first instanceof Token || strtoupper($first->text) !== 'ARRAY') {
            return null;
        }
        $elements = Tree::child($source, ['array_expr']);
        if ($elements !== null) {
            return self::elements($elements, $scope);
        }
        $query = Tree::child($source, ['select_with_parens']);
        if ($query === null || $scope->queries === null) {
            return null;
        }
        $bound = $scope->queries->bind($query, $scope);
        $width = RowShape::width($bound);
        if ($width !== null && $width !== 1) {
            throw new InvalidSql(InputViolation::ScalarQueryWidth, $source);
        }
        return new ArraySubquery($source, $bound);
    }

    /**
     * Recognizes value op ANY|SOME|ALL (array) where the parenthesized operand is an expression rather than a query.
     * @throws InvalidSql
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function comparison(Node $source, Scope $scope): ?ArrayComparison
    {
        $operands = array_values(array_filter($source->children, static fn (Node|Token $child): bool => $child instanceof Node && $child->name === 'a_expr'));
        $quantifier = Tree::child($source, ['sub_type']);
        $operator = Tree::child($source, ['subquery_Op']);
        if ($scope->identifiers->dialect !== Dialect::PostgreSql || $quantifier === null || $operator === null || count($operands) !== 2) {
            return null;
        }
        $choice = \SqlSemantics\Binding\Scalar\Operator\QuantifiedOperator::read($operator, $scope);
        $binder = new ExpressionBinder();
        return new ArrayComparison($source, $binder->bind($operands[0], $scope), $choice->operator, $choice->negated, Quantifier::from(strtoupper(Tree::text($quantifier))), $binder->bind($operands[1], $scope));
    }

    /**
     * Binds one bracketed level; nested brackets become constructors of the next dimension sharing one element type.
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public static function elements(Node $list, Scope $scope): ArrayConstructor
    {
        $nested = Tree::child($list, ['array_expr_list']);
        $items = $nested === null
            ? array_map(static fn (Node $item): Expression => (new ExpressionBinder())->bind($item, $scope), Tree::outer(Tree::child($list, ['expr_list']) ?? new Node('expr_list', 0, []), ['a_expr']))
            : array_map(static fn (Node $item): ArrayConstructor => self::elements($item, $scope), Tree::outer($nested, ['array_expr']));
        $nestedTypes = [];
        foreach ($items as $item) {
            if ($item->type->identity instanceof \SqlSemantics\Type\Identity\ArrayStorage) {
                $nestedTypes[] = $item->type->identity->element;
            }
        }
        $element = $nestedTypes !== [] && count($nestedTypes) === count($items)
            ? \SqlSemantics\Type\CommonStorage::resolve($scope->identifiers->dialect, $nestedTypes)
            : (new TypeResolution($scope->identifiers->dialect, $scope->diagnostics()))->common($items, $list);
        return new ArrayConstructor($list, $element, $items);
    }
}
