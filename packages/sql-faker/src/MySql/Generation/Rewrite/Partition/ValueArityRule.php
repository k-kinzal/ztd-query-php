<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Partition;

use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * parse_tree_partitions.cc requires a common value width, and two or more fields for LIST rows.
 * Runs after kind selection, expression grouping and scalar-list disambiguation.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/parse_tree_partitions.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_partition.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_partition.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/sql_partition.cc
 */
final class ValueArityRule implements RewriteRule
{
    /**
     * Uses declared columns or the first ALTER value shape independently for each definition list.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $widths = [];
        $shape = new ValueShape();
        foreach (array_reverse($sequence->occurrences('opt_part_values')) as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $scope = array_search('part_def_list', $origin->rules, true);
            if ($scope === false) {
                continue;
            }
            $list = $sequence->nameAt($range[0] + 1) === 'IN_SYM';
            $start = $range[0] + ($list ? 2 : 3);
            $tokens = array_slice($sequence->terminals, $start, $range[1] - $start);
            $group = $origin->ancestors[$scope];
            if (!isset($widths[$group])) {
                $width = count($shape->rows($tokens, $list)[0] ?? []);
                if ($list && ($tokens[1]->name ?? null) === '(') {
                    $width = max(2, $width);
                }
                $widths[$group] = $this->declaredWidth($sequence, $origin->ancestor('partition_clause')) ?? max(1, min(16, $width));
            }
            $replacement = $shape->resize($tokens, $list, $widths[$group]);
            if (array_column($replacement, 'name') === array_column($tokens, 'name')) {
                continue;
            }
            foreach ($replacement as $offset => $token) {
                if ($token->id === PHP_INT_MIN) {
                    $replacement[$offset] = $sequence->insertedFor($token->name, $id, 'sql/parse_tree_partitions.cc:value-arity', $offset);
                }
            }
            $sequence = $sequence->replace($start, count($tokens), $replacement, 'sql/parse_tree_partitions.cc:value-arity');
        }
        return $sequence;
    }

    /**
     * Expression partitioning has one value per bound; COLUMNS declares its own tuple width.
     */
    public function declaredWidth(TerminalSequence $sequence, ?int $clause): ?int
    {
        $type = $clause === null ? null : $sequence->child($clause, 'part_type_def');
        $range = $type === null ? null : $sequence->range($type->id);
        if ($range === null) {
            return null;
        }
        if ($sequence->nameAt($range[0] + 1) !== 'COLUMNS') {
            return 1;
        }
        return count((new ValueShape())->split(array_slice($sequence->terminals, $range[0] + 3, $range[1] - $range[0] - 4)));
    }
}
