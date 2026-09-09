<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Query;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Preserves the frame boundary ordering and interval units required by window.cc.
 */
final class WindowFrameRule implements RewriteRule
{
    /**
     * Repairs each frame independently, including frames nested in offset expressions.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('window_frame_between') as $between) {
            $bounds = array_values(array_filter($sequence->productions, static fn ($p): bool => $p->parent === $between && $p->rule === 'window_frame_bound'));
            if (count($bounds) !== 2) {
                continue;
            }
            [$start, $end] = [$bounds[0]->id, $bounds[1]->id];
            if ($this->rank($sequence, $start) === 4) {
                $sequence = $this->unbounded($sequence, $start, 'PRECEDING_SYM');
            }
            if ($this->rank($sequence, $end) === 0 || $this->rank($sequence, $start) > $this->rank($sequence, $end)) {
                $sequence = $this->unbounded($sequence, $end, 'FOLLOWING_SYM');
            }
        }
        foreach ($sequence->occurrences('opt_window_frame_clause') as $frame) {
            $units = $sequence->child($frame, 'window_frame_units');
            $range = $units === null ? null : $sequence->range($units->id);
            if ($range === null || $sequence->nameAt($range[0]) !== 'ROWS_SYM') {
                continue;
            }
            foreach ($sequence->terminals as $terminal) {
                if ($terminal->name === 'INTERVAL_SYM' && $terminal->ancestor('opt_window_frame_clause') === $frame
                    && in_array($terminal->rules[count($terminal->rules) - 1], ['window_frame_start', 'window_frame_bound'], true)) {
                    $sequence = $sequence->replace($range[0], 1, [$sequence->inserted('RANGE_SYM', $sequence->terminals[$range[0]], 'sql/window.cc:frame-interval')], 'sql/window.cc:frame-interval');
                    break;
                }
            }
        }
        return $sequence;
    }

    /**
     * Classifies boundary categories without comparing runtime offset values.
     */
    public function rank(TerminalSequence $sequence, int $bound): int
    {
        $range = $sequence->range($bound);
        if ($range === null) {
            return 2;
        }
        $last = $sequence->nameAt($range[1] - 1);
        return match (true) {
            $sequence->nameAt($range[0]) === 'UNBOUNDED_SYM' => $last === 'PRECEDING_SYM' ? 0 : 4,
            $last === 'PRECEDING_SYM' => 1,
            $last === 'FOLLOWING_SYM' => 3,
            default => 2,
        };
    }

    /**
     * Retains the original derivation while replacing a forbidden boundary.
     */
    public function unbounded(TerminalSequence $sequence, int $bound, string $direction): TerminalSequence
    {
        $range = $sequence->range($bound);
        return $range === null ? $sequence : $sequence->replace($range[0], $range[1] - $range[0], [
            $sequence->insertedFor('UNBOUNDED_SYM', $bound, 'sql/window.cc:frame-order'),
            $sequence->insertedFor($direction, $bound, 'sql/window.cc:frame-order', 1),
        ], 'sql/window.cc:frame-order');
    }
}
