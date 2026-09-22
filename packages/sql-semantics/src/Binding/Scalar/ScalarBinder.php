<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use LogicException;
use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\NullFacts;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\TypeResolution;
use SqlSemantics\Model\Expression;
use SqlSemantics\Type\Nullability;

/**
 * Binds function, conditional, cast, and subquery expression productions.
 *
 * @visibility SqlSemantics
 */
final class ScalarBinder
{
    /**
     * Retains all expression operands, including predicates and window clauses.
     * @throws \SqlSemantics\Binding\Statement\UnclassifiedSql
     */
    public function bind(Node $node, Scope $scope, bool $rowSubquery = false): Expression
    {
        $context = (new ContextValueBinder())->bind($node, $scope);
        if ($context !== null) {
            return $context;
        }
        if ($scope->identifiers->dialect === \SqlSemantics\Dialect::Sqlite && ($node->children[0] ?? null) instanceof Token && strtoupper($node->children[0]->text) === 'RAISE') {
            return RaiseBinder::bind($node, $scope);
        }
        $collated = ConversionBinder::collation($node, $scope);
        if ($collated !== null) {
            return $collated;
        }
        $children = Tree::significant($node);
        $subquery = $this->nestedQuery($node);
        if ($subquery !== null && $scope->queries !== null) {
            return $this->subquery($node, $subquery, $scope, $rowSubquery);
        }
        $invocation = $this->functionInvocation($node, $scope);
        if ($invocation !== null) {
            return $invocation;
        }
        $operands = $this->operands($node, $scope);
        $cast = ConversionBinder::cast($node, $scope, $operands);
        if ($cast !== null) {
            return $cast;
        }
        if ($node->name === 'case_expr' || ($node->children[0] ?? null) instanceof Token && strtoupper($node->children[0]->text) === 'CASE') {
            return $this->conditional($node, $scope, $operands);
        }
        $symbol = isset($children[0]) ? strtoupper(Tree::text($children[0])) : $node->name;
        if (isset($children[1]) && Tree::text($children[1]) === '(') {
            return (new FunctionRules())->bind($symbol, $operands, $node, $scope);
        }
        $operator = $this->operator($children);
        if ($operator !== '' && $operands !== []) {
            return (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->operator($operator, $operands, $node);
        }
        throw new \SqlSemantics\Binding\Statement\UnclassifiedSql('Unclassified expression ' . $node->name . ': ' . $node->toString());
    }

    /**
     * Binds a function together with its FILTER, WITHIN GROUP, and OVER clauses.
     */
    public function functionInvocation(Node $node, Scope $scope): ?Expression
    {
        if ($node->name === 'func_expr' || $node->name === 'set_function_specification') {
            $application = Tree::child($node, ['func_application']);
            if ($application !== null) {
                return (new FunctionRules())->bind(strtoupper(Tree::text($application->children[0])), $this->operands($application, $scope), $node, $scope);
            }
        }
        return null;
    }

    /**
     * @return list<Expression>
     */
    public function operands(Node $node, Scope $scope): array
    {
        $operands = [];
        foreach ($node->children as $child) {
            if (!$child instanceof Node || !Tree::hasTokens($child) || in_array($child->name, ['func_name', 'function_call_keyword', 'Typename', 'cast_type', 'typetoken', 'collate', 'collate_clause', 'opt_collate', 'filter_clause', 'over_clause', 'windowing_clause', 'opt_windowing_clause', 'within_group_clause', 'opt_sort_clause', 'orderby_opt', 'sortlist', 'order_clause'], true)) {
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
            if ($child instanceof Token || in_array($child->name, ['comp_op', 'qual_Op', 'subquery_Op', 'likeop', 'between_op', 'in_op', 'not', 'sub_type', 'all_or_any'], true)) {
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
        $branches = [];
        foreach (Tree::outer($node, ['when_clause', 'case_exprlist', 'when_list']) as $when) {
            $expressions = Tree::outer($when, ['a_expr', 'expr']);
            for ($index = 0; $index + 1 < count($expressions); $index += 2) {
                $branches[] = new \SqlSemantics\Model\Scalar\Conditional\When((new ExpressionBinder())->bind($expressions[$index], $scope), (new ExpressionBinder())->bind($expressions[$index + 1], $scope));
            }
        }
        $default = Tree::child($node, ['case_default', 'case_else', 'opt_else']);
        $otherwise = $default === null ? null : ($this->operands($default, $scope)[0] ?? null);
        $values = [...array_map(static fn ($branch): Expression => $branch->result, $branches), ...($otherwise === null ? [] : [$otherwise])];
        $type = (new TypeResolution($scope->identifiers->dialect, $scope->diagnostics()))->common($values, $node);
        $facts = new \SqlSemantics\Model\Scalar\ExpressionFacts($type, $otherwise === null ? Nullability::MaybeNull : NullFacts::alternatives($values));
        $argument = Tree::child($node, ['case_arg', 'case_operand', 'opt_expr']);
        $value = $argument === null ? null : ($this->operands($argument, $scope)[0] ?? null);
        return $value === null
            ? new \SqlSemantics\Model\Scalar\Conditional\SearchedCase($facts, $node, $branches, $otherwise)
            : new \SqlSemantics\Model\Scalar\Conditional\SimpleCase($facts, $node, $value, $branches, $otherwise);
    }

    /**
     * Keeps the nested query and its row dependencies on the expression itself.
     *
     * @throws LogicException
     */
    public function subquery(Node $source, Node $node, Scope $scope, bool $rowSubquery = false): Expression
    {
        return (new QueryExpressionBinder())->bind($source, $node, $scope, $rowSubquery);
    }

    /**
     * Finds this operation's query operand without entering a scalar argument.
     */
    public function nestedQuery(Node $source): ?Node
    {
        $boundaries = ['SelectStmt', 'select_with_parens', 'subquery', 'subselect', 'select', 'a_expr', 'expr', 'c_expr', 'func_arg_list', 'exprlist', 'func_application'];
        foreach ($source->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            foreach (Tree::outer($child, $boundaries) as $node) {
                if (in_array($node->name, ['SelectStmt', 'select_with_parens', 'subquery', 'subselect', 'select'], true)) {
                    return $node;
                }
            }
        }
        return null;
    }

}
