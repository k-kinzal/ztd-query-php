<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Query;

use Override;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y:insertSelectOptions merges parenthesized query options into the same SelectStmt.
 */
final class SelectOptionsRule implements RewriteRule
{
    /**
     * Keeps the innermost sort, limit options and WITH, stopping at new SELECT or set-operation nodes.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $byId = [];
        $children = [];
        $active = [];
        foreach ($sequence->productions as $production) {
            $byId[$production->id] = $production;
            $children[$production->parent ?? -1][$production->rule] ??= $production;
        }
        foreach ($sequence->terminals as $terminal) {
            foreach ($terminal->ancestors as $ancestor) {
                $active[$ancestor] = true;
            }
        }
        $seen = [];
        foreach ($sequence->occurrences('select_no_parens') as $id) {
            foreach (['sort' => ['sort_clause', 'opt_sort_clause'], 'limit' => ['select_limit', 'opt_select_limit'], 'with' => ['with_clause', 'with_clause']] as $kind => $rules) {
                $clause = $children[$id][$rules[0]] ?? $children[$id][$rules[1]] ?? null;
                if ($clause === null || !isset($active[$clause->id])) {
                    continue;
                }
                $query = $this->query($byId, $clause);
                $range = $sequence->range($clause->id);
                if ($query === null || $range === null) {
                    continue;
                }
                if (isset($seen[$query][$kind])) {
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'gram.y:insertSelectOptions');
                }
                $seen[$query][$kind] = true;
            }
        }
        return $sequence;
    }

    /**
     * Only grammar wrappers returning the identical SelectStmt share option ownership.
     * @param array<int, ProductionOccurrence> $byId
     */
    public function query(array $byId, ProductionOccurrence $clause): ?int
    {
        $node = $clause->parent === null ? null : ($byId[$clause->parent] ?? null);
        if ($node !== null && in_array($node->rule, ['opt_sort_clause', 'opt_select_limit'], true)) {
            $node = $node->parent === null ? null : ($byId[$node->parent] ?? null);
        }
        if ($node === null || $node->rule !== 'select_no_parens') {
            return null;
        }
        $query = $node->id;
        while ($node->parent !== null && isset($byId[$node->parent])) {
            $node = $byId[$node->parent];
            if (!in_array($node->rule, ['select_no_parens', 'select_with_parens', 'select_clause'], true)) {
                break;
            }
            if ($node->rule === 'select_no_parens') {
                $query = $node->id;
            }
        }
        return $query;
    }
}
