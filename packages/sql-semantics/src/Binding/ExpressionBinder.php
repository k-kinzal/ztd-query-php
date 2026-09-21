<?php

declare(strict_types=1);

namespace SqlSemantics\Binding;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Model\Expression;

/**
 * Lowers scalar grammar nodes by their tree structure, preserving expression boundaries.
 *
 * @visibility SqlSemantics
 */
final class ExpressionBinder
{
    /**
     * Binds one expression using the namespace at its evaluation stage.
     */
    public function bind(Node|Token $node, Scope $scope): Expression
    {
        if ($node instanceof Token) {
            return $this->token($node, $scope);
        }
        if (in_array($node->name, ['columnref', 'simple_ident'], true)) {
            return $scope->column($scope->identifiers->parts($node), $node);
        }
        $children = Tree::significant($node);
        if (count($children) === 1) {
            return $this->bind($children[0], $scope);
        }
        if (count($children) === 3 && Tree::text($children[0]) === '(' && Tree::text($children[2]) === ')') {
            return $this->bind($children[1], $scope);
        }
        if ($node->name === 'expr' && $this->qualified($children)) {
            return $scope->column($scope->identifiers->parts($node), $node);
        }
        if (isset($children[1]) && Tree::text($children[1]) === '(') {
            return $this->call($node, $children, $scope);
        }
        $expression = $this->operation($node, $children, $scope);
        if ($expression !== null) {
            return $expression;
        }

        Tree::unsupported($node, 'expression');
    }

    /**
     * Resolves a scalar terminal as a literal, parameter, or column.
     */
    public function token(Token $token, Scope $scope): Expression
    {
        $literal = (new LiteralBinder($scope->identifiers->dialect))->bind($token);
        if ($literal !== null) {
            return $literal;
        }
        if (in_array($token->name, ['IDENT', 'IDENT_QUOTED', 'ID'], true)) {
            return $scope->column([$scope->identifiers->name($token)], $token);
        }

        Tree::unsupported($token, 'expression terminal');
    }

    /**
     * @param list<Node|Token> $children
     */
    public function qualified(array $children): bool
    {
        if (!in_array(count($children), [3, 5], true)) {
            return false;
        }
        foreach ($children as $index => $child) {
            if ($index % 2 === 1 && Tree::text($child) !== '.') {
                return false;
            }
            if ($index % 2 === 0 && (!$child instanceof Node || $child->name !== 'nm')) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<Node|Token> $children
     */
    public function call(Node $node, array $children, Scope $scope): Expression
    {
        $name = strtoupper(Tree::text($children[0]));
        if (!in_array($name, ['COALESCE', 'NULLIF'], true)) {
            Tree::unsupported($node, 'function');
        }
        $operands = [];
        foreach (array_slice($children, 2, -1) as $child) {
            if ($child instanceof Node) {
                foreach (Tree::outer($child, ['a_expr', 'expr']) as $argument) {
                    $operands[] = $this->bind($argument, $scope);
                }
            }
        }

        return (new ExpressionRules($scope->identifiers->dialect))->call($name, $operands, $node);
    }

    /**
     * @param list<Node|Token> $children
     */
    public function operation(Node $node, array $children, Scope $scope): ?Expression
    {
        $rules = new ExpressionRules($scope->identifiers->dialect);
        if (count($children) === 2 && in_array(strtoupper(Tree::text($children[0])), ['+', '-', 'NOT'], true)) {
            return $rules->operator(Tree::text($children[0]), [$this->bind($children[1], $scope)], $node);
        }
        if (count($children) >= 2) {
            $tail = strtoupper(implode(' ', array_map(Tree::text(...), array_slice($children, 1))));
            if (in_array($tail, ['IS NULL', 'IS NOT NULL', 'ISNULL', 'NOTNULL'], true)) {
                return $rules->operator(in_array($tail, ['IS NULL', 'ISNULL'], true) ? 'IS NULL' : 'IS NOT NULL', [$this->bind($children[0], $scope)], $node);
            }
        }
        if (count($children) === 3 && in_array(strtoupper(Tree::text($children[1])), ['+', '-', '*', '=', '<>', '!=', '<', '>', '<=', '>=', 'AND', 'OR', 'IS', '<=>'], true)) {
            return $rules->operator(Tree::text($children[1]), [$this->bind($children[0], $scope), $this->bind($children[2], $scope)], $node);
        }

        return null;
    }
}
