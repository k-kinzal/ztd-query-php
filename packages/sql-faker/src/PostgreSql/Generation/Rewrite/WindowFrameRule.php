<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Implements the frame_extent rejection conditions in PostgreSQL 17.2 gram.y.
 */
final class WindowFrameRule implements RewriteRule
{
    /**
     * Completes invalid frame endpoints without changing valid offsets or frames in nested expressions.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('frame_extent') as $extent) {
            $bounds = array_values(array_filter($sequence->productions, static fn ($p): bool => $p->parent === $extent && $p->rule === 'frame_bound'));
            if ($bounds === []) {
                continue;
            }
            $start = $bounds[0]->id;
            if ($this->rank($sequence, $start) === 4) {
                $sequence = $this->unbounded($sequence, $start, 'PRECEDING');
            }
            $end = $bounds[1]->id ?? null;
            $endRank = $end === null ? 2 : $this->rank($sequence, $end);
            if ($end !== null && ($endRank === 0 || $this->rank($sequence, $start) > $endRank)) {
                $sequence = $this->unbounded($sequence, $end, 'FOLLOWING');
            } elseif ($end === null && $this->rank($sequence, $start) > 2) {
                $range = $sequence->range($start);
                if ($range !== null && !($sequence->nameAt($range[0] - 1) === 'BETWEEN'
                    && ($sequence->terminals[$range[0] - 1]->rewrite ?? null) === 'postgresql.frame-order')) {
                    $sequence = $sequence->replace($range[1], 0, [
                        $sequence->insertedFor('AND', $extent, 'postgresql.frame-order'),
                        $sequence->insertedFor('UNBOUNDED', $extent, 'postgresql.frame-order', 1),
                        $sequence->insertedFor('FOLLOWING', $extent, 'postgresql.frame-order', 2),
                    ], 'postgresql.frame-order');
                    $sequence = $sequence->replace($range[0], 0, [
                        $sequence->insertedFor('BETWEEN', $extent, 'postgresql.frame-order'),
                    ], 'postgresql.frame-order');
                }
            }
        }
        return $sequence;
    }

    /**
     * Assigns the category used by gram.y to compare start and end boundaries.
     */
    public function rank(TerminalSequence $sequence, int $bound): int
    {
        $range = $sequence->range($bound);
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

    /**
     * Attaches the replacement endpoint to the original frame-bound occurrence for tracing.
     */
    public function unbounded(TerminalSequence $sequence, int $bound, string $direction): TerminalSequence
    {
        $range = $sequence->range($bound);
        return $range === null ? $sequence : $sequence->replace($range[0], $range[1] - $range[0], [
            $sequence->insertedFor('UNBOUNDED', $bound, 'postgresql.frame-order'),
            $sequence->insertedFor($direction, $bound, 'postgresql.frame-order', 1),
        ], 'postgresql.frame-order');
    }
}
