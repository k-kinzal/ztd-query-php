<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;

/**
 * Navigates query boundaries without walking into a different query scope.
 *
 * @visibility SqlSemantics
 */
final class QueryNodes
{
    /**
     * @param list<string> $names
     * @return list<Node>
     */
    public static function local(Node $node, array $names): array
    {
        if (in_array($node->name, $names, true)) {
            return [$node];
        }
        $result = [];
        foreach ($node->children as $child) {
            if (!$child instanceof Node || (in_array($child->name, ['SelectStmt', 'select_stmt', 'select', 'select_with_parens', 'subquery', 'table_subquery', 'select_derived_union', 'with_clause', 'wqlist', 'a_expr', 'expr', 'func_expr'], true) && !in_array($node->name, ['parse_toplevel', 'stmtmulti', 'toplevel_stmt', 'stmt', 'start_entry', 'sql_statement', 'simple_statement_or_begin', 'simple_statement', 'input', 'cmdlist', 'ecmd', 'cmdx', 'cmd', 'query', 'verb_clause', 'statement'], true))) {
                continue;
            }
            array_push($result, ...self::local($child, $names));
        }
        return $result;
    }

    /**
     * Finds the outer query body, retaining compound-query precedence.
     */
    public static function body(Node $node): Node
    {
        if (in_array($node->name, ['simple_select', 'query_specification', 'select_part2', 'select_derived2', 'oneselect', 'values_clause'], true) || self::setOperator($node) !== null) {
            return $node;
        }
        foreach ($node->children as $child) {
            if ($child instanceof Node && $child->tokens() !== [] && !in_array($child->name, ['with_clause', 'wqlist'], true)) {
                $body = self::body($child);
                if (in_array($body->name, ['simple_select', 'query_specification', 'select_part2', 'select_derived2', 'oneselect', 'values_clause'], true) || self::setOperator($body) !== null) {
                    return $body;
                }
            }
        }
        return $node;
    }

    /**
     * Reads only this production's set operator, not operators in its operands.
     */
    public static function setOperator(Node $node): ?string
    {
        foreach (Tree::significant($node) as $child) {
            $text = strtoupper(Tree::text($child));
            if (in_array($text, ['UNION', 'UNION ALL', 'INTERSECT', 'INTERSECT ALL', 'EXCEPT', 'EXCEPT ALL'], true)) {
                $all = Tree::child($node, ['set_quantifier', 'union_option']);
                return $text . ($all !== null && strtoupper(Tree::text($all)) === 'ALL' ? ' ALL' : '');
            }
        }
        return null;
    }
    /**
     * @return array<string, list<Node>> Source-bearing outer query clauses
     */
    public static function clauses(Node $source): array
    {
        $names = [];
        $pending = [$source];
        while ($pending !== []) {
            $node = array_pop($pending);
            if (str_contains($node->name, 'clause') || in_array($node->name, ['distinct', 'select_options', 'windowdefn_list', 'groupby_opt', 'orderby_opt', 'limit_opt'], true)) {
                $names[$node->name] = true;
            }
            foreach ($node->children as $child) {
                if ($child instanceof Node && $child->tokens() !== []) {
                    $pending[] = $child;
                }
            }
        }
        $clauses = [];
        foreach (array_keys($names) as $name) {
            $nodes = self::local($source, [$name]);
            if ($nodes !== []) {
                $clauses[$name] = $nodes;
            }
        }
        return $clauses;
    }

}
