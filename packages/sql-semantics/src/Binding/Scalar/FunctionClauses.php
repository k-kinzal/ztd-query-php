<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Scalar;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;

/**
 * Reads clauses owned by one invocation, excluding its argument expressions.
 *
 * @visibility SqlSemantics
 */
final class FunctionClauses
{
    /**
     * @param list<string> $names Clause names
     */
    public static function find(Node $source, array $names): ?Node
    {
        if (in_array($source->name, $names, true) && Tree::hasTokens($source)) {
            return $source;
        }
        foreach ($source->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            if (in_array($child->name, $names, true) && Tree::hasTokens($child)) {
                return $child;
            }
            if (in_array($child->name, ['a_expr', 'expr', 'c_expr', 'b_expr', 'bit_expr', 'bool_pri', 'simple_expr', 'exprlist', 'nexprlist', 'expr_list', 'func_arg_list', 'over_clause', 'windowing_clause', 'filter_clause', 'sort_clause', 'orderby_opt', 'sortlist', 'order_clause', 'within_group_clause', 'SelectStmt', 'select', 'subquery'], true)) {
                continue;
            }
            $found = self::find($child, $names);
            if ($found !== null) {
                return $found;
            }
        }
        return null;
    }

    /**
     * Identifies the all-rows argument of this invocation, excluding nested calls.
     */
    public static function allRows(Node $source): bool
    {
        $tokens = $source->tokens();
        foreach ($tokens as $index => $token) {
            if ($token->text === '(') {
                return ($tokens[$index + 1]->text ?? null) === '*' && ($tokens[$index + 2]->text ?? null) === ')';
            }
        }
        return false;
    }

    /**
     * @return list<\SqlSemantics\Model\Expression> Ordered-set arguments evaluated for each input row
     */
    public static function orderedInputs(Node $source, \SqlSemantics\Binding\Scope $scope): array
    {
        $clause = self::find($source, ['within_group_clause']);
        if ($clause === null) {
            return [];
        }
        return array_map(static fn (Node $node): \SqlSemantics\Model\Expression => (new \SqlSemantics\Binding\ExpressionBinder())->bind($node, $scope), Tree::outer($clause, ['a_expr']));
    }

    /**
     * Reads the row ordering owned by this aggregate invocation.
     */
    public static function ordering(Node $source): Node
    {
        $order = self::find($source, ['opt_sort_clause', 'orderby_opt', 'order_clause', 'within_group_clause', 'sortlist']);
        if ($order?->name === 'sortlist') {
            return new Node('orderby_opt', 0, [$order]);
        }
        return $order ?? new Node('no_ordering', 0, []);
    }
}
