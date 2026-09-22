<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Editing;

use SqlParser\Parser\Node;
use SqlSemantics\Binding\Query\QueryNodes;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree as SqlTree;
use SqlSemantics\Model\Transformation\SourceEdit;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Locates clause productions in the current scope, including empty optional productions.
 *
 * @visibility SqlSemantics
 */
final class ClauseEditor
{
    /**
     * Replaces one complete clause and preserves enclosing and sibling statements.
     * @throws InvalidStructure
     */
    public static function replace(BoundStatement $statement, string $role, SqlTree $replacement): SqlTree
    {
        $source = in_array($role, ['outputs', 'where', 'from', 'groupBy', 'having', 'distinct'], true) && $statement instanceof \SqlSemantics\Model\BoundQuery ? QueryNodes::body($statement->source) : $statement->source;
        if ($role === 'rows' && isset($statement->queries[0])) {
            return SourceEdit::replace($statement->source, $statement->sql, $statement->queries[0]->source, $replacement);
        }
        $names = self::names($role);
        $nodes = QueryNodes::local($source, $names);
        $node = $nodes[0] ?? null;
        if ($node === null && $role === 'orderBy') {
            $query = QueryNodes::local($source, ['select_no_parens'])[0] ?? null;
            if ($query !== null) {
                return SourceEdit::replace($statement->source, $statement->sql, $query, new SqlTree('ordered', [\SqlSemantics\Model\Sql\Source::read($query), $replacement]));
            }
        }
        if ($node === null) {
            throw new InvalidStructure('The statement has no structural position for ' . $role . '.');
        }
        if (in_array($node->name, ['where_opt_ret', 'upsert'], true)) {
            $replacement = self::combinedReturning($node, $role, $replacement);
        }
        return SourceEdit::replace($statement->source, $statement->sql, $node, $replacement);
    }

    /**
     * @return non-empty-list<string>
     * @throws InvalidStructure
     */
    public static function names(string $role): array
    {
        return match ($role) {
            'table' => ['relation_expr', 'table_ident'],
            'outputs' => ['opt_target_list', 'target_list', 'select_item_list', 'selcollist'],
            'where' => ['where_clause', 'opt_where_clause', 'where_or_current_clause', 'where_opt', 'where_opt_ret'],
            'from' => ['opt_from_clause', 'from_clause', 'from', 'select_from'],
            'groupBy' => ['group_clause', 'opt_group_clause', 'groupby_opt'],
            'having' => ['having_clause', 'opt_having_clause', 'having_opt'],
            'orderBy' => ['opt_sort_clause', 'sort_clause', 'opt_order_clause', 'order_clause', 'orderby_opt'],
            'pagination' => ['select_limit', 'opt_select_limit', 'opt_limit_clause', 'limit_clause', 'limit_opt'],
            'distinct' => ['distinct_clause', 'select_options', 'distinct'],
            'returning' => ['returning_clause', 'where_opt_ret', 'upsert'],
            'writes' => ['set_clause_list', 'update_list', 'setlist'],
            'rows' => ['insert_values', 'values_clause', 'values_list', 'values_row_list', 'row_value_list', 'values'],
            'ctes' => ['with_clause', 'with', 'wqlist'],
            default => throw new InvalidStructure('Unknown statement component: ' . $role),
        };
    }

    /**
     * SQLite groups WHERE and RETURNING in one grammar production.
     */
    public static function combinedReturning(Node $node, string $role, SqlTree $replacement): SqlTree
    {
        $atoms = \SqlSemantics\Model\Sql\Source::read($node)->atoms();
        $returning = array_search('RETURNING', array_map(static fn ($atom): string => strtoupper($atom->text), $atoms), true);
        if ($role === 'where') {
            return new SqlTree('where_opt_ret', [$replacement, ...($returning === false ? [] : array_slice($atoms, $returning))]);
        }
        return new SqlTree('where_opt_ret', [...array_slice($atoms, 0, $returning === false ? count($atoms) : $returning), $replacement]);
    }
}
