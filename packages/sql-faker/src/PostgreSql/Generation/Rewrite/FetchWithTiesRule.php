<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Completes the ordering required by gram.y/insertSelectOptions for FETCH WITH TIES.
 */
final class FetchWithTiesRule implements RewriteRule
{
    /**
     * Supplies an absent ORDER BY and removes the incompatible SKIP LOCKED wait policy.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('limit_clause') as $id) {
            $range = $sequence->range($id);
            if ($range === null || $sequence->nameAt($range[1] - 1) !== 'TIES') {
                continue;
            }
            $query = $sequence->terminals[$range[0]]->ancestor('select_no_parens');
            if ($query === null) {
                continue;
            }
            $sequence = $this->ordered($sequence, $query, $range[0]);
            foreach ($sequence->occurrences('opt_nowait_or_skip') as $wait) {
                $waitRange = $sequence->range($wait);
                if ($waitRange !== null && $sequence->nameAt($waitRange[0]) === 'SKIP'
                    && $sequence->terminals[$waitRange[0]]->ancestor('select_no_parens') === $query) {
                    $sequence = $sequence->replace($waitRange[0], $waitRange[1] - $waitRange[0], [], 'gram.y:WITH-TIES-SKIP-LOCKED');
                }
            }
        }
        return $sequence;
    }

    /**
     * Adds a positional ordering only when the same query has no sort clause.
     */
    public function ordered(TerminalSequence $sequence, int $query, int $offset): TerminalSequence
    {
        foreach ($sequence->terminals as $terminal) {
            if (($terminal->within('sort_clause') || $terminal->within('opt_sort_clause'))
                && $terminal->ancestor('select_no_parens') === $query) {
                return $sequence;
            }
        }
        $locking = $sequence->child($query, 'for_locking_clause');
        $lockRange = $locking === null ? null : $sequence->range($locking->id);
        if ($lockRange !== null) {
            $offset = min($offset, $lockRange[0]);
        }
        $sort = $sequence->child($query, 'opt_sort_clause');
        $scope = $sort->id ?? $query;
        $source = 'gram.y:WITH-TIES-ORDER-BY';
        return $sequence->replace($offset, 0, [
            $sequence->insertedFor('ORDER', $scope, $source),
            $sequence->insertedFor('BY', $scope, $source, 1),
            $sequence->insertedFor('ICONST', $scope, $source, 2),
        ], $source);
    }
}
