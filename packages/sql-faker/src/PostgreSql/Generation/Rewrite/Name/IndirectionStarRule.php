<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Name;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y/makeColumnRef and check_indirection allow a star only at the end of its own chain.
 */
final class IndirectionStarRule implements RewriteRule
{
    /**
     * Removes non-final star components without touching multiplication or independent nested chains.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        $ends = [];
        $stars = [];
        foreach ($sequence->occurrences('indirection_el') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $origin = $sequence->terminals[$range[0]];
            $parent = count($origin->rules) - 2;
            while (in_array($origin->rules[$parent] ?? null, ['indirection', 'opt_indirection'], true)) {
                --$parent;
            }
            $scope = $origin->ancestors[$parent + 1];
            $ends[$scope] = max($ends[$scope] ?? 0, $range[1]);
            if ($origin->name === '.' && $sequence->nameAt($range[0] + 1) === '*') {
                $stars[$range[0]] = [$scope, $range[1]];
            }
        }
        krsort($stars);
        foreach ($stars as $start => [$scope, $end]) {
            if ($end < $ends[$scope]) {
                $sequence = $sequence->replace($start, $end - $start, [], 'gram.y:check_indirection');
            }
        }
        return $sequence;
    }
}
