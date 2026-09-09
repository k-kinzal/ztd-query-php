<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Partition;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * parse_tree_partitions.cc/PT_part_value_item_max forbids MAXVALUE in a VALUES IN list.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/parse_tree_partitions.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_partition.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_partition.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/sql_partition.cc
 */
final class ListValueRule implements RewriteRule
{
    /**
     * Uses an integer list value and preserves range partition bounds.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->terminals as $index => $terminal) {
            if ($terminal->name === 'MAX_VALUE_SYM' && $terminal->ancestor('part_values_in') !== null) {
                $sequence = $sequence->replace($index, 1, [
                    $terminal->replaced('NUM', 'sql/parse_tree_partitions.cc:PT_part_value_item_max'),
                ], 'sql/parse_tree_partitions.cc:PT_part_value_item_max');
            }
        }
        foreach ($sequence->occurrences('part_value_item') as $id) {
            $range = $sequence->range($id);
            if ($range === null || $sequence->nameAt($range[0]) !== '(' || !$sequence->terminals[$range[0]]->within('part_values_in')) {
                continue;
            }
            $sequence = $sequence->replace($range[0], 0, [
                $sequence->insertedFor('+', $id, 'sql/sql_yacc.yy:part_values_in-expression'),
            ], 'sql/sql_yacc.yy:part_values_in-expression');
        }
        return $sequence;
    }
}
