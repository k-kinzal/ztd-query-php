<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Token;

/**
 * One source-based structural transformation, before any spelling is selected.
 */
interface RewriteRule
{
    /**
     * Preserves unaffected occurrences and records the origin of each structural change.
     */
    public function rewrite(TerminalSequence $sequence): TerminalSequence;
}
