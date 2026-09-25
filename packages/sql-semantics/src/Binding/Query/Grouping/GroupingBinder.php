<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\Grouping;

use SqlParser\Parser\Node;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Query\Grouping;

/**
 * Binds a GROUP BY clause into its keys and grouping-set constructs: PostgreSQL ROLLUP, CUBE, GROUPING SETS and
 * `()`, MySQL `WITH ROLLUP`, `ROLLUP(...)`, `CUBE(...)` and MySQL 5.x descending keys, and SQLite plain keys.
 * @visibility SqlSemantics
 */
final class GroupingBinder
{
    /**
     * Returns the GROUP BY list of a query body and whether PostgreSQL GROUP BY DISTINCT removes duplicate sets.
     *
     * @return array{list<Expression|Grouping\GroupingConstruct|Grouping\DescendingGroupKey>, bool}
     */
    public static function bind(Node $body, Scope $scope): array
    {
        $clause = QueryNodes::local($body, ['group_clause', 'opt_group_clause', 'groupby_opt'])[0] ?? null;
        if ($clause === null || !Tree::hasTokens($clause)) {
            return [[], false];
        }
        $quantifier = Tree::child($clause, ['set_quantifier']);
        $list = Tree::child($clause, ['group_by_list']);
        if ($list !== null) {
            return [self::items($list, $scope), $quantifier !== null && strtoupper(Tree::text($quantifier)) === 'DISTINCT'];
        }
        $groupList = Tree::child($clause, ['group_list']);
        if ($groupList === null) {
            return [array_map(static fn (Node $node): Expression => (new ExpressionBinder())->bind($node, $scope), Tree::outer($clause, ['expr'])), false];
        }
        $keys = self::keys($groupList, $scope);
        $words = Tree::keywords($clause);
        return match (true) {
            in_array('CUBE', $words, true) => [[new Grouping\Cube(array_map(static fn (Expression|Grouping\DescendingGroupKey $key): Expression => $key instanceof Expression ? $key : $key->key, $keys))], false],
            in_array('ROLLUP', $words, true), in_array('WITH ROLLUP', $words, true) => [[new Grouping\Rollup($keys)], false],
            default => [$keys, false],
        };
    }

    /**
     * Binds the items of a PostgreSQL group_by_list in written order.
     *
     * @return list<Expression|Grouping\GroupingConstruct>
     */
    public static function items(Node $list, Scope $scope): array
    {
        $items = [];
        foreach (Tree::outer($list, ['group_by_item']) as $item) {
            $construct = Tree::child($item, ['empty_grouping_set', 'rollup_clause', 'cube_clause', 'grouping_sets_clause']);
            $items[] = match ($construct?->name) {
                'empty_grouping_set' => new Grouping\EmptyGroupingSet(),
                'rollup_clause' => new Grouping\Rollup(self::expressions($construct, $scope)),
                'cube_clause' => new Grouping\Cube(self::expressions($construct, $scope)),
                'grouping_sets_clause' => new Grouping\GroupingSets(self::items(Tree::child($construct, ['group_by_list']) ?? $construct, $scope)),
                default => (new ExpressionBinder())->bind(Tree::outer($item, ['a_expr'])[0] ?? $item, $scope),
            };
        }
        return $items;
    }

    /**
     * Binds the keys listed by a PostgreSQL ROLLUP or CUBE.
     *
     * @return list<Expression>
     */
    public static function expressions(Node $construct, Scope $scope): array
    {
        return array_map(static fn (Node $node): Expression => (new ExpressionBinder())->bind($node, $scope), Tree::outer($construct, ['a_expr']));
    }

    /**
     * Binds the keys of a MySQL group_list; MySQL 5.x writes an optional ASC or DESC after each key, and only DESC
     * changes the order of the groups.
     *
     * @return list<Expression|Grouping\DescendingGroupKey>
     */
    public static function keys(Node $list, Scope $scope): array
    {
        $keys = [];
        foreach ($list->children as $child) {
            if (!$child instanceof Node) {
                continue;
            }
            if ($child->name === 'group_list') {
                array_push($keys, ...self::keys($child, $scope));
            } elseif ($child->name === 'grouping_expr' || $child->name === 'order_ident') {
                $keys[] = (new ExpressionBinder())->bind(Tree::outer($child, ['expr'])[0] ?? $child, $scope);
            }
            $direction = $child->name === 'order_dir' ? $child : ($child->name === 'grouping_expr' ? Tree::child($child, ['ordering_direction']) : null);
            $last = array_key_last($keys);
            if ($direction !== null && $last !== null && strtoupper(Tree::text($direction)) === 'DESC' && $keys[$last] instanceof Expression) {
                $keys[$last] = new Grouping\DescendingGroupKey($keys[$last]);
            }
        }
        return $keys;
    }
}
