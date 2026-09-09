<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * PT_subquery and partition contextualization forbid subqueries in these statement expressions.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/parse_tree_nodes.cc
 */
final class SubqueryContextRule implements RewriteRule
{
    /**
     * Replaces only the operand containing a forbidden subquery; ordinary SELECT subqueries remain intact.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('subquery') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            if (array_intersect(['handler_stmt', 'purge', 'install_stmt', 'part_type_def', 'opt_sub_part'], $origin->rules) === []) {
                continue;
            }
            $position = array_search($id, $origin->ancestors, true);
            for ($index = ($position === false ? 0 : $position) - 1; $index >= 0; --$index) {
                if (!in_array($origin->rules[$index], ['expr', 'bool_pri', 'predicate', 'bit_expr', 'simple_expr'], true)) {
                    continue;
                }
                $operand = $origin->ancestors[$index];
                $scope = $sequence->range($operand);
                if ($scope !== null) {
                    $sequence = $sequence->replace($scope[0], $scope[1] - $scope[0], [
                        $sequence->insertedFor('NUM', $operand, 'sql/parse_tree_nodes.cc:PT_subquery'),
                    ], 'sql/parse_tree_nodes.cc:PT_subquery');
                }
                break;
            }
        }
        return $sequence;
    }
}
