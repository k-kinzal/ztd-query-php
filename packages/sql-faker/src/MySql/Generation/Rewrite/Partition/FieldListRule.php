<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Partition;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_const.h:MAX_REF_PARTS limits partition and subpartition field lists to sixteen names.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_const.h
 */
final class FieldListRule implements RewriteRule
{
    /**
     * Retains the leading fields and their occurrence IDs without limiting unrelated name lists.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $byId = [];
        foreach ($sequence->productions as $production) {
            $byId[$production->id] = $production;
        }
        foreach ($sequence->productions as $list) {
            if (!in_array($list->rule, ['name_list', 'part_field_item_list', 'sub_part_field_list'], true) || $list->parent === null) {
                continue;
            }
            $parent = $byId[$list->parent] ?? null;
            if ($parent?->rule === 'opt_name_list') {
                $parent = $parent->parent === null ? null : ($byId[$parent->parent] ?? null);
            }
            if (!in_array($parent?->rule, ['part_type_def', 'opt_sub_part', 'part_field_list'], true)) {
                continue;
            }
            $seen = 0;
            foreach (array_reverse($sequence->occurrences('ident')) as $field) {
                $range = $sequence->range($field);
                if ($range === null || !in_array($list->id, $sequence->terminals[$range[0]]->ancestors, true) || ++$seen <= 16) {
                    continue;
                }
                $listRange = $sequence->range($list->id);
                if ($listRange !== null && $sequence->nameAt($range[0] - 1) === ',') {
                    $sequence = $sequence->replace($range[0] - 1, $listRange[1] - $range[0] + 1, [], 'sql/sql_const.h:MAX_REF_PARTS');
                }
                break;
            }
        }
        return $sequence;
    }
}
