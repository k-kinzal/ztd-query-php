<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query;

use function assert;

use SqlParser\Lexer\Token;
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
     * Legacy MySQL productions between a SELECT operand and its trailing ORDER BY and LIMIT.
     * @var list<string>
     */
    private const MODIFIER_CONTAINERS = ['opt_order_clause', 'opt_limit_clause', 'select_part2', 'select_into', 'select_from', 'select_init2', 'query_specification', 'select_init2_derived', 'select_part2_derived', 'opt_select_from', 'table_expression', 'opt_union_order_or_limit', 'union_order_or_limit', 'order_or_limit', 'opt_limit_clause_init'];

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
            if (!$child instanceof Node || ($child->name === 'table_factor' && self::isBody($child)) || (in_array($child->name, ['SelectStmt', 'select_stmt', 'select', 'select_with_parens', 'subquery', 'subselect', 'table_subquery', 'select_derived_union', 'insert_query_expression', 'create_select', 'with_clause', 'wqlist', 'a_expr', 'expr', 'func_expr', 'func_application'], true) && !in_array($node->name, ['parse_toplevel', 'stmtmulti', 'toplevel_stmt', 'stmt', 'start_entry', 'sql_statement', 'simple_statement_or_begin', 'simple_statement', 'input', 'cmdlist', 'ecmd', 'cmdx', 'cmd', 'query', 'verb_clause', 'statement'], true))) {
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
        $parenthesized = self::parenthesizedQuery($node);
        if ($parenthesized !== null) {
            return self::body($parenthesized);
        }
        if ($node->name === 'derived_table_list') {
            $reference = Tree::outer($node, ['table_ref'])[0] ?? null;
            $factor = $reference === null ? null : Tree::child($reference, ['table_factor']);
            return $factor !== null && self::isBody($factor) ? self::body($factor) : $node;
        }
        if (Tree::child($node, ['union_clause', 'opt_union_clause', 'union_opt']) !== null) {
            return self::legacyCompound($node);
        }
        if (self::isBody($node)) {
            return in_array($node->name, ['query_expression_body', 'select_derived_union'], true) ? self::derivedCompound($node) : $node;
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
     * The union's trailing ORDER BY and LIMIT, which the legacy grammar attaches to the last
     * operand, move to a `legacy_compound_tail` child of the outermost compound.
     */
    public static function legacyCompound(Node $node): Node
    {
        $left = Tree::child($node, ['select_part2']) ?? Tree::child($node, ['create_select', 'create_view_select', 'create_view_select_paren', 'select_paren']) ?? $node;
        $left = $left->name === 'create_view_select_paren' ? (Tree::child($left, ['create_view_select']) ?? $left) : $left;
        $tail = Tree::child($node, ['union_clause', 'opt_union_clause', 'union_opt']);
        $modifiers = [];
        while ($tail !== null) {
            $union = Tree::child($tail, ['union_list']);
            if ($union === null && Tree::child($tail, ['union_order_or_limit', 'order_or_limit']) !== null) {
                $modifiers = Tree::outer($tail, ['order_clause', 'limit_clause']);
                break;
            }
            $right = $union === null ? null : Tree::child($union, ['select_init', 'select_paren', 'query_specification']);
            if ($union === null || $right === null) {
                Tree::invalid($tail, 'UNION operands');
            }
            $container = Tree::child($right, ['select_init2']) ?? $right;
            $body = Tree::child($container, ['select_part2']) ?? Tree::child($right, ['select_paren']) ?? $right;
            $tail = Tree::child($container, ['union_clause', 'opt_union_clause', 'union_opt']);
            if ($body->name === 'select_part2' && $tail === null) {
                $modifiers = self::trailingModifiers($body);
                $body = self::withoutTrailingModifiers($body);
            }
            $operator = array_values(array_filter($union->children, static fn ($child): bool => !$child instanceof Node || $child->name === 'union_option'));
            $left = new Node('legacy_compound', 0, [$left, ...$operator, $body]);
        }
        if ($modifiers !== [] && $left->name === 'legacy_compound') {
            $left = new Node('legacy_compound', 0, [...$left->children, new Node('legacy_compound_tail', 0, $modifiers)]);
        }
        return $left;
    }

    /**
     * Returns the node holding a compound query's own ORDER BY and LIMIT, excluding its operands.
     */
    public static function compoundTail(Node $source, Node $body): Node
    {
        if ($body->name === 'legacy_compound') {
            return Tree::child($body, ['legacy_compound_tail']) ?? new Node('legacy_compound_tail', 0, []);
        }
        $scope = self::modifierScope($source, $body);
        if ($scope === $body) {
            return new Node('compound_tail', 0, []);
        }
        return new Node($scope->name, $scope->ordinal, array_values(array_filter($scope->children, static fn (Node|Token $child): bool => $child instanceof Node && Tree::hasTokens($child) && !self::contains($child, $body))));
    }

    /**
     * Finds the enclosing production that carries the body's ORDER BY, LIMIT, and locking clauses.
     */
    public static function modifierScope(Node $source, Node $body): Node
    {
        if ($body->name === 'table_factor') {
            return $body;
        }
        $node = $body;
        while (true) {
            $parent = self::parentOf($source, $node);
            if ($parent === null) {
                return $source === $body ? $body : $source;
            }
            foreach ($parent->children as $child) {
                if ($child !== $node && $child instanceof Node && Tree::hasTokens($child)) {
                    return $parent;
                }
            }
            $node = $parent;
        }
    }

    /**
     * Reports whether the target is the node itself or one of its descendants.
     */
    public static function contains(Node $node, Node $target): bool
    {
        return $node === $target || self::parentOf($node, $target) !== null;
    }

    /**
     * Finds the production whose immediate children include the target.
     */
    public static function parentOf(Node $root, Node $target): ?Node
    {
        foreach ($root->children as $child) {
            if ($child === $target) {
                return $root;
            }
            if ($child instanceof Node) {
                $found = self::parentOf($child, $target);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    /**
     * Moves the trailing ORDER BY and LIMIT of a legacy MySQL compound's last operand to the compound.
     */
    public static function derivedCompound(Node $node): Node
    {
        $children = [];
        $modifiers = [];
        $last = null;
        foreach ($node->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['opt_union_order_or_limit', 'union_order_or_limit'], true)) {
                array_push($modifiers, ...Tree::outer($child, ['order_clause', 'limit_clause']));
                continue;
            }
            $children[] = $child;
            if ($child instanceof Node && $child->name === 'query_specification') {
                $last = count($children) - 1;
            }
        }
        if ($last !== null) {
            $operand = $children[$last];
            assert($operand instanceof Node);
            $trailing = self::trailingModifiers($operand);
            if ($trailing !== []) {
                $children[$last] = self::withoutTrailingModifiers($operand);
                array_push($modifiers, ...$trailing);
            }
        }
        if ($modifiers === []) {
            return $node;
        }
        return new Node('legacy_compound', 0, [...$children, new Node('legacy_compound_tail', 0, $modifiers)]);
    }

    /**
     * @return list<Node> ORDER BY and LIMIT clauses written after the last legacy UNION operand
     */
    public static function trailingModifiers(Node $node): array
    {
        $found = [];
        foreach ($node->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            if (in_array($child->name, ['order_clause', 'limit_clause'], true)) {
                $found[] = $child;
            } elseif (in_array($child->name, self::MODIFIER_CONTAINERS, true)) {
                array_push($found, ...self::trailingModifiers($child));
            }
        }
        return $found;
    }

    /**
     * Removes the ORDER BY and LIMIT that legacy MySQL grammar attaches to the last UNION operand but applies to the whole union.
     */
    public static function withoutTrailingModifiers(Node $node): Node
    {
        $children = [];
        foreach ($node->children as $child) {
            if ($child instanceof Node && in_array($child->name, ['opt_order_clause', 'opt_limit_clause', 'order_clause', 'limit_clause'], true)) {
                continue;
            }
            $children[] = $child instanceof Node && in_array($child->name, self::MODIFIER_CONTAINERS, true) ? self::withoutTrailingModifiers($child) : $child;
        }
        return new Node($node->name, $node->ordinal, $children);
    }

    /**
     * Finds the legacy MySQL production that holds a leading query and its UNION tail as siblings.
     */
    public static function legacyContainer(Node $node): ?Node
    {
        foreach (Tree::outer($node, ['insert_values', 'insert_query_expression', 'create3', 'view_select_aux']) as $container) {
            if (Tree::child($container, ['union_clause', 'opt_union_clause', 'union_opt']) !== null) {
                return $container;
            }
        }
        return null;
    }

    /**
     * Recognizes query bodies, including SELECT productions in legacy table factors.
     */
    public static function isBody(Node $node): bool
    {
        if (in_array($node->name, ['table_factor', 'create_select', 'create_view_select'], true)) {
            $children = Tree::significant($node);
            return $children !== [] && (strtoupper(Tree::text($children[0])) === 'SELECT' || self::parenthesizedQuery($node) !== null);
        }
        return in_array($node->name, ['simple_select', 'query_specification', 'select_part2', 'select_part2_derived', 'select_derived2', 'oneselect', 'values_clause', 'explicit_table', 'derived_table_list'], true) || self::setOperator($node) !== null;
    }

    /**
     * Returns the query inside a legacy MySQL table factor written as a parenthesized derived union.
     */
    public static function parenthesizedQuery(Node $node): ?Node
    {
        if ($node->name !== 'table_factor') {
            return null;
        }
        $children = Tree::significant($node);
        if (count($children) !== 3 || Tree::text($children[0]) !== '(' || !$children[1] instanceof Node || $children[1]->name !== 'select_derived_union') {
            return null;
        }
        $inner = $children[1];
        if (self::setOperator($inner) !== null) {
            return $inner;
        }
        $references = Tree::outer($inner, ['table_ref']);
        $factor = count($references) === 1 ? Tree::child($references[0], ['table_factor']) : null;
        return $factor !== null && self::isBody($factor) ? $inner : null;
    }

    /**
     * Returns the table list of a FROM clause: MySQL 5.6 `select_from` also holds WHERE, GROUP BY,
     * HAVING, ORDER BY and LIMIT, whose subqueries must not contribute relations to this FROM.
     */
    public static function fromList(Node $from): Node
    {
        return $from->name === 'select_from' ? (Tree::child($from, ['join_table_list']) ?? $from) : $from;
    }

    /**
     * Returns the two operands of a joined table without entering its ON condition or USING list,
     * whose subqueries own their relations.
     *
     * @return list<Node>
     */
    public static function joinOperands(Node $join): array
    {
        $operands = [];
        foreach ($join->children as $child) {
            if ($child instanceof Node && !in_array($child->name, ['expr', 'a_expr', 'join_qual', 'using_list'], true)) {
                array_push($operands, ...Tree::outer($child, ['table_ref', 'table_reference', 'table_factor']));
            }
        }
        return $operands;
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
