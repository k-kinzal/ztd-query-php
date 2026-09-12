<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * PT_subquery rejects INTO in subqueries; sql_lex.cc/new_set_operation_query allows it only in the final SELECT.
 * sql_yacc.yy/view_query_block disables SELECT destinations throughout a view definition.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/parse_tree_nodes.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class IntoClauseRule implements RewriteRule
{
    /**
     * Removes view, nested or non-final INTO clauses, preserving ordinary outer output destinations.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $destinations = [];
        foreach ($sequence->occurrences('into_clause') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $statement = $origin->ancestor('select_stmt');
            $source = $origin->ancestor('subquery') === null ? null : 'sql/parse_tree_nodes.cc:PT_subquery:into';
            if ($origin->within('view_query_block')) {
                $source = 'sql/sql_yacc.yy:view_query_block:into';
            }
            for ($index = $range[1]; $source === null && $index < count($sequence->terminals); ++$index) {
                $following = $sequence->terminals[$index];
                $last = count($following->ancestors) - 1;
                if (in_array($following->name, ['UNION_SYM', 'EXCEPT_SYM', 'INTERSECT_SYM'], true)
                    && ($following->rules[$last] ?? null) === 'query_expression_body'
                    && in_array($following->ancestors[$last], $origin->ancestors, true)) {
                    $source = 'sql/sql_lex.cc:new_set_operation_query:into';
                }
            }
            if ($source === null && $statement !== null) {
                $source = isset($destinations[$statement]) ? 'sql/parse_tree_nodes.cc:PT_select_stmt:multiple-into' : null;
                $destinations[$statement] = true;
            }
            if ($source !== null) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], $source);
            }
        }
        return $sequence;
    }
}
