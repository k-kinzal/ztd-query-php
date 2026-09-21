<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use LogicException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Ast\TypeReader;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\NullFacts;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\TypeResolution;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\ExpressionKind;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Binds function, conditional, cast, and subquery expression productions.
 *
 * @visibility SqlSemantics
 */
final class ScalarBinder
{
    /**
     * Retains all expression operands, including predicates and window clauses.
     */
    public function bind(Node $node, Scope $scope): Expression
    {
        $children = Tree::significant($node);
        $text = strtoupper(Tree::text($node));
        $subquery = $this->nestedQuery($node);
        if ($subquery !== null && $scope->queries !== null) {
            return $this->subquery($node, $subquery, $scope);
        }
        $operands = $this->operands($node, $scope);
        $typeNode = Tree::child($node, ['Typename', 'cast_type', 'typetoken']);
        if ($typeNode !== null && (str_starts_with($text, 'CAST ') || str_contains($text, ' :: '))) {
            $type = (new TypeReader($scope->identifiers->dialect))->read($typeNode);
            return new Expression(ExpressionKind::Cast, $type, $operands[0]->nullability ?? Nullability::Unknown, $node, $operands, symbol: 'explicit');
        }
        if ($node->name === 'case_expr' || str_starts_with($text, 'CASE ')) {
            return $this->conditional($node, $scope, $operands);
        }
        $symbol = isset($children[0]) ? strtoupper(Tree::text($children[0])) : $node->name;
        if (isset($children[1]) && Tree::text($children[1]) === '(') {
            return (new FunctionRules())->bind($symbol, $operands, $node, $scope);
        }
        if ($node->name === 'func_expr' || $node->name === 'set_function_specification') {
            $base = $operands[0] ?? null;
            if ($base !== null) {
                return new Expression(ExpressionKind::Window, $base->type, $base->nullability, $node, $operands, symbol: $base->symbol);
            }
        }
        $operator = $this->operator($children);
        if ($operator !== '' && $operands !== []) {
            return (new ExpressionRules($scope->identifiers->dialect))->operator($operator, $operands, $node);
        }
        return new Expression(ExpressionKind::Operator, new TypeDescriptor($scope->identifiers->dialect, 'unknown'), Nullability::Unknown, $node, $operands, symbol: $node->name);
    }

    /**
     * @return list<Expression>
     */
    public function operands(Node $node, Scope $scope): array
    {
        $operands = [];
        foreach ($node->children as $child) {
            if (!$child instanceof Node || $child->tokens() === [] || in_array($child->name, ['func_name', 'function_call_keyword', 'Typename', 'cast_type', 'typetoken', 'collate', 'collate_clause', 'opt_collate'], true)) {
                continue;
            }
            if (in_array($child->name, ['a_expr', 'b_expr', 'c_expr', 'expr', 'bool_pri', 'predicate', 'bit_expr', 'simple_expr', 'func_application', 'func_expr', 'sum_expr', 'window_func_call', 'columnref', 'simple_ident', 'term'], true)) {
                $operands[] = (new ExpressionBinder())->bind($child, $scope);
            } else {
                array_push($operands, ...$this->operands($child, $scope));
            }
        }
        return $operands;
    }

    /**
     * @param list<Node|Token> $children
     */
    public function operator(array $children): string
    {
        $words = [];
        foreach ($children as $child) {
            if ($child instanceof Token || in_array($child->name, ['comp_op', 'qual_Op', 'subquery_Op', 'likeop', 'between_op', 'in_op'], true)) {
                $text = strtoupper(Tree::text($child));
                if (!in_array($text, ['(', ')', ',', 'AND'], true)) {
                    $words[] = $text;
                }
            }
        }
        return implode(' ', $words);
    }

    /**
     * @param list<Expression> $operands
     */
    public function conditional(Node $node, Scope $scope, array $operands): Expression
    {
        $values = [];
        foreach (Tree::outer($node, ['when_clause', 'case_exprlist', 'when_list']) as $when) {
            $expressions = Tree::outer($when, ['a_expr', 'expr']);
            for ($index = 1; $index < count($expressions); $index += 2) {
                $values[] = (new ExpressionBinder())->bind($expressions[$index], $scope);
            }
        }
        $default = Tree::child($node, ['case_default', 'case_else', 'opt_else']);
        if ($default !== null) {
            array_push($values, ...$this->operands($default, $scope));
        }
        $type = (new TypeResolution($scope->identifiers->dialect))->common($values, $node);
        $nullability = $default === null ? Nullability::MaybeNull : NullFacts::alternatives($values);
        return new Expression(ExpressionKind::CaseExpression, $type, $nullability, $node, $operands, symbol: 'CASE');
    }

    /**
     * Keeps the nested query and its row dependencies on the expression itself.
     *
     * @throws LogicException
     */
    public function subquery(Node $source, Node $node, Scope $scope): Expression
    {
        $context = $scope->queries;
        if ($context === null) {
            throw new LogicException('Subquery binding requires a query context.');
        }
        $query = $context->bind($node, $scope);
        $text = strtoupper(Tree::text($source));
        $exists = str_starts_with($text, 'EXISTS');
        $membership = str_contains($text, ' IN ');
        $type = $exists || $membership ? (new TypeResolution($scope->identifiers->dialect))->boolean() : ($query->outputs[0]->expression->type ?? new TypeDescriptor($scope->identifiers->dialect, 'unknown'));
        $operands = [];
        if ($source !== $node) {
            foreach ($source->children as $child) {
                if ($child instanceof Node && in_array($child->name, ['a_expr', 'expr', 'bit_expr'], true)) {
                    $operands[] = (new ExpressionBinder())->bind($child, $scope);
                }
            }
        }
        array_push($operands, ...array_map(static fn ($output): Expression => $output->expression, $query->outputs));
        return new Expression(ExpressionKind::Subquery, $type, $exists ? Nullability::NotNull : Nullability::MaybeNull, $source, $operands, symbol: $exists ? 'EXISTS' : ($membership ? 'IN' : 'SCALAR'), query: $query);
    }
    /**
     * Finds this operation's query operand without entering a scalar argument.
     */
    public function nestedQuery(Node $source): ?Node
    {
        $boundaries = ['SelectStmt', 'select_with_parens', 'subquery', 'select', 'a_expr', 'expr', 'c_expr', 'func_arg_list', 'exprlist', 'func_application'];
        foreach ($source->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            foreach (Tree::outer($child, $boundaries) as $node) {
                if (in_array($node->name, ['SelectStmt', 'select_with_parens', 'subquery', 'select'], true)) {
                    return $node;
                }
            }
        }
        return null;
    }

}
