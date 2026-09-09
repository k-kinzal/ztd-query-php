<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Routine;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Aggregate arguments cannot have output modes; ordered-set direct VARIADIC types need coordinated lists.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L8521-L8585
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L18776-L18848
 */
final class AggregateArgumentRule implements RewriteRule
{
    /**
     * Uses IN for output modes and direct variadic arguments, preserving function modes outside aggregates.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('arg_class') as $id) {
            $range = $sequence->range($id);
            if ($range === null || $sequence->terminals[$range[0]]->ancestor('aggr_arg') === null) {
                continue;
            }
            $names = array_slice($sequence->names(), $range[0], $range[1] - $range[0]);
            $replace = in_array('OUT_P', $names, true) || in_array('INOUT', $names, true);
            $owner = $sequence->terminals[$range[0]]->ancestor('aggr_args');
            if ($names === ['VARIADIC'] && $owner !== null) {
                foreach ($sequence->terminals as $index => $terminal) {
                    if ($terminal->name === 'ORDER' && $terminal->ancestor('aggr_args') === $owner && $index > $range[0]) {
                        $replace = true;
                    }
                }
            }
            if ($replace) {
                $source = 'gram.y:aggr_arg:input-mode';
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [$sequence->insertedFor('IN_P', $id, $source)], $source);
            }
        }
        return $sequence;
    }
}
