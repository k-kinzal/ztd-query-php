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
            if (!$child instanceof Node || ($child->name === 'table_factor' && self::isBody($child)) || (in_array($child->name, ['SelectStmt', 'select_stmt', 'select', 'select_with_parens', 'subquery', 'subselect', 'table_subquery', 'select_derived_union', 'insert_query_expression', 'create_select', 'with_clause', 'wqlist', 'a_expr', 'expr', 'func_expr'], true) && !in_array($node->name, ['parse_toplevel', 'stmtmulti', 'toplevel_stmt', 'stmt', 'start_entry', 'sql_statement', 'simple_statement_or_begin', 'simple_statement', 'input', 'cmdlist', 'ecmd', 'cmdx', 'cmd', 'query', 'verb_clause', 'statement'], true))) {
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
        if ($node->name === 'derived_table_list') {
            $reference = Tree::outer($node, ['table_ref'])[0] ?? null;
            $factor = $reference === null ? null : Tree::child($reference, ['table_factor']);
            return $factor !== null && self::isBody($factor) ? $factor : $node;
        }
        if (Tree::child($node, ['union_clause', 'opt_union_clause']) !== null) {
            return self::legacyCompound($node);
        }
        if (self::isBody($node)) {
            return $node;
        }
        foreach ($node->children as $child) {
            if ($child instanceof Node && Tree::hasTokens($child) && !in_array($child->name, ['with_clause', 'wqlist'], true)) {
                $body = self::body($child);
                if (self::isBody($body)) {
                    return $body;
                }
            }
        }
        return $node;
    }

    /**
     * Normalizes legacy right-recursive UNION syntax into its left-associative query graph.
     */
    public static function legacyCompound(Node $node): Node
    {
        $left = Tree::child($node, ['select_part2']) ?? $node;
        $tail = Tree::child($node, ['union_clause', 'opt_union_clause']);
        while ($tail !== null) {
            $union = Tree::child($tail, ['union_list']);
            $right = $union === null ? null : Tree::child($union, ['select_init', 'select_paren', 'query_specification']);
            if ($union === null || $right === null) {
                Tree::invalid($tail, 'UNION operands');
            }
            $container = Tree::child($right, ['select_init2']) ?? $right;
            $body = Tree::child($container, ['select_part2']) ?? $right;
            $operator = array_values(array_filter($union->children, static fn ($child): bool => !$child instanceof Node || $child->name === 'union_option'));
            $left = new Node('query_expression_body', 0, [$left, ...$operator, $body]);
            $tail = Tree::child($container, ['union_clause', 'opt_union_clause']);
        }
        return $left;
    }

    /**
     * Recognizes query bodies, including SELECT productions in legacy table factors.
     */
    public static function isBody(Node $node): bool
    {
        if (in_array($node->name, ['table_factor', 'create_select'], true)) {
            $children = Tree::significant($node);
            return $children !== [] && strtoupper(Tree::text($children[0])) === 'SELECT';
        }
        return in_array($node->name, ['simple_select', 'query_specification', 'select_part2', 'select_part2_derived', 'select_derived2', 'oneselect', 'values_clause', 'explicit_table', 'derived_table_list'], true) || self::setOperator($node) !== null;
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
                if ($child instanceof Node && Tree::hasTokens($child)) {
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
