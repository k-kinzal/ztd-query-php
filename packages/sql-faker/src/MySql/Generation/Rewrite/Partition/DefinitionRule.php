<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Partition;

use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * PT_part_definition requires one partition kind for the list; PT_partition checks its declared count.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/parse_tree_partitions.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.7.44/sql/sql_partition.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_partition.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-5.6.51/sql/sql_partition.cc
 */
final class DefinitionRule implements RewriteRule
{
    /**
     * Uses the explicit partition type, or the first definition for an ADD/REORGANIZE list.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $kinds = [];
        foreach (array_reverse($sequence->occurrences('part_definition')) as $id) {
            $range = $sequence->range($id);
            $values = $sequence->child($id, 'opt_part_values');
            if ($range === null || $values === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $list = array_search('part_def_list', $origin->rules, true);
            if ($list === false) {
                continue;
            }
            $group = $origin->ancestors[$list];
            $valueRange = $sequence->range($values->id);
            $current = $valueRange === null ? 'HASH' : ($sequence->nameAt($valueRange[0] + 1) === 'IN_SYM' ? 'LIST' : 'RANGE');
            if (!isset($kinds[$group])) {
                $clause = $origin->ancestor('partition_clause');
                $kinds[$group] = $this->declaredKind($sequence, $clause) ?? $current;
                $sequence = $this->inferCount($sequence, $clause);
            }
            if ($current === $kinds[$group]) {
                continue;
            }
            $sequence = $this->replaceValues($sequence, $id, $values->id, $kinds[$group]);
        }
        return $sequence;
    }

    /**
     * Returns the enclosing declaration's kind, leaving ALTER lists to infer it from their values.
     *
     * @return 'HASH'|'LIST'|'RANGE'|null
     */
    public function declaredKind(TerminalSequence $sequence, ?int $clause): ?string
    {
        $type = $clause === null ? null : $sequence->child($clause, 'part_type_def');
        $range = $type === null ? null : $sequence->range($type->id);
        return $range === null ? null : match ($sequence->nameAt($range[0])) {
            'RANGE_SYM' => 'RANGE', 'LIST_SYM' => 'LIST', default => 'HASH'
        };
    }

    /**
     * Lets MySQL infer the count from the explicit list instead of checking an independent number.
     */
    public function inferCount(TerminalSequence $sequence, ?int $clause): TerminalSequence
    {
        $count = $clause === null ? null : $sequence->child($clause, 'opt_num_parts');
        $range = $count === null ? null : $sequence->range($count->id);
        return $range === null ? $sequence : $sequence->replace($range[0], $range[1] - $range[0], [], 'sql/parse_tree_partitions.cc:explicit-partition-count');
    }

    /**
     * Replaces incompatible values while preserving the definition and its occurrence identity.
     *
     * @param 'HASH'|'LIST'|'RANGE' $kind
     */
    public function replaceValues(TerminalSequence $sequence, int $definition, int $values, string $kind): TerminalSequence
    {
        $range = $sequence->range($values);
        $identifier = $sequence->child($definition, 'ident');
        $nameRange = $identifier === null ? null : $sequence->range($identifier->id);
        $start = $range[0] ?? $nameRange[1] ?? null;
        if ($start === null) {
            return $sequence;
        }
        $names = match ($kind) {
            'RANGE' => ['VALUES', 'LESS_SYM', 'THAN_SYM', 'MAX_VALUE_SYM'],
            'LIST' => ['VALUES', 'IN_SYM', '(', 'NUM', ')'],
            'HASH' => [],
        };
        $replacement = [];
        foreach ($names as $offset => $name) {
            $replacement[] = $sequence->insertedFor($name, $values, 'sql/parse_tree_partitions.cc:partition-kind', $offset);
        }
        return $sequence->replace($start, $range === null ? 0 : $range[1] - $range[0], $replacement, 'sql/parse_tree_partitions.cc:partition-kind');
    }
}
