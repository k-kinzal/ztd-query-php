<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Alter;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy alter_order_list shifts comma plus identifier before reducing alter_list.
 * Keeping ORDER BY last prevents a following nonreserved action from becoming an order item.
 */
final class OrderByRule implements RewriteRule
{
    /**
     * Orders each independent ALTER action list without crossing nested or separate statement scopes.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $roots = [];
        foreach ($sequence->productions as $production) {
            if ($production->rule === 'alter_list') {
                $roots[$production->id] = $roots[$production->parent ?? -1] ?? $production->id;
            }
        }
        foreach (array_unique($roots) as $id) {
            $sequence = $this->reorder($sequence, $id);
        }
        return $sequence;
    }

    /**
     * Stably moves complete ORDER BY actions after other actions, retaining original terminal identities.
     */
    public function reorder(TerminalSequence $sequence, int $id): TerminalSequence
    {
        $range = $sequence->range($id);
        if ($range === null) {
            return $sequence;
        }
        $chunks = [[]];
        $separators = [];
        foreach (array_slice($sequence->terminals, $range[0], $range[1] - $range[0]) as $terminal) {
            if ($terminal->name === ',' && ($terminal->rules[count($terminal->rules) - 1] ?? null) === 'alter_list') {
                $chunks[] = [];
                $separators[] = $terminal;
            } else {
                $chunks[count($chunks) - 1][] = $terminal;
            }
        }
        $orders = [];
        $others = [];
        foreach ($chunks as $chunk) {
            if (($chunk[0]->name ?? null) === 'ORDER_SYM') {
                $orders[] = $chunk;
            } else {
                $others[] = $chunk;
            }
        }
        $ordered = [...$others, ...$orders];
        if ($ordered === $chunks) {
            return $sequence;
        }
        $output = [];
        foreach ($ordered as $index => $chunk) {
            if ($index !== 0) {
                $output[] = $separators[$index - 1];
            }
            array_push($output, ...$chunk);
        }
        return $sequence->replace($range[0], $range[1] - $range[0], $output, 'sql_yacc.yy:alter_list:order-by-last');
    }
}
