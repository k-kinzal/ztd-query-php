<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Implements the relative boundary order required by window.c/sqlite3WindowAlloc.
 */
final class WindowFrameRule implements RewriteRule
{
    /**
     * Completes short FOLLOWING frames and moves an earlier end to UNBOUNDED FOLLOWING.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('frame_opt') as $id) {
            $start = $sequence->child($id, 'frame_bound_s');
            if ($start === null) {
                continue;
            }
            $end = $sequence->child($id, 'frame_bound_e');
            if ($this->rank($sequence, $start->id) <= ($end === null ? 2 : $this->rank($sequence, $end->id))) {
                continue;
            }
            if ($end === null) {
                $range = $sequence->range($start->id);
                if ($range !== null && !($sequence->nameAt($range[0] - 1) === 'BETWEEN'
                    && ($sequence->terminals[$range[0] - 1]->rewrite ?? null) === 'sqlite.frame-order')) {
                    $sequence = $sequence->replace($range[1], 0, [
                        $sequence->insertedFor('AND', $id, 'sqlite.frame-order'),
                        $sequence->insertedFor('UNBOUNDED', $id, 'sqlite.frame-order', 1),
                        $sequence->insertedFor('FOLLOWING', $id, 'sqlite.frame-order', 2),
                    ], 'sqlite.frame-order');
                    $sequence = $sequence->replace($range[0], 0, [$sequence->insertedFor('BETWEEN', $id, 'sqlite.frame-order')], 'sqlite.frame-order');
                }
            } else {
                $range = $sequence->range($end->id);
                if ($range !== null) {
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                        $sequence->insertedFor('UNBOUNDED', $end->id, 'sqlite.frame-order'),
                        $sequence->insertedFor('FOLLOWING', $end->id, 'sqlite.frame-order', 1),
                    ], 'sqlite.frame-order');
                }
            }
        }
        return $sequence;
    }

    /**
     * Orders boundary categories exactly as sqlite3WindowAlloc's source comment specifies.
     */
    public function rank(TerminalSequence $sequence, int $production): int
    {
        $range = $sequence->range($production);
        if ($range === null) {
            return 2;
        }
        $first = $sequence->nameAt($range[0]);
        $last = $sequence->nameAt($range[1] - 1);
        return match (true) {
            $first === 'UNBOUNDED' => $last === 'PRECEDING' ? 0 : 4,
            $last === 'PRECEDING' => 1,
            $last === 'FOLLOWING' => 3,
            default => 2,
        };
    }
}
