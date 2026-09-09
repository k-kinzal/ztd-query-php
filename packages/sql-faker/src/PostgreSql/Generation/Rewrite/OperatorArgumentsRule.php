<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * REL_17_2 gram.y oper_argtypes rejects a lone type and prescribes NONE for a missing unary argument.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y
 */
final class OperatorArgumentsRule implements RewriteRule
{
    /**
     * Adds the missing left argument only within oper_argtypes, never in operator expressions or type modifiers.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('oper_argtypes') as $occurrence) {
            $range = $sequence->range($occurrence);
            if ($range === null || $this->hasArgumentSeparator($sequence, $range[0], $range[1])) {
                continue;
            }
            $open = $sequence->terminals[$range[0]];
            $sequence = $sequence->replace($range[0] + 1, 0, [
                $sequence->inserted('NONE', $open, 'pg.operator-arguments'),
                $sequence->inserted(',', $open, 'pg.operator-arguments', 1),
            ], 'pg.operator-arguments');
        }
        return $sequence;
    }

    /**
     * Distinguishes an argument separator from commas inside a type's modifiers.
     */
    public function hasArgumentSeparator(TerminalSequence $sequence, int $start, int $end): bool
    {
        $depth = 0;
        for ($index = $start; $index < $end; ++$index) {
            $name = $sequence->nameAt($index);
            if ($name === '(') {
                ++$depth;
            } elseif ($name === ')') {
                --$depth;
            } elseif ($name === ',' && $depth === 1) {
                return true;
            }
        }
        return false;
    }
}
