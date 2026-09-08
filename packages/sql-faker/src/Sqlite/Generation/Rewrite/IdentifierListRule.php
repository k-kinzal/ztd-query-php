<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * parse.y/parserAddExprIdListTerm only accepts COLLATE and sort order while loading legacy schemas.
 */
final class IdentifierListRule implements RewriteRule
{
    /**
     * Removes legacy-only identifier suffixes, preserving ORDER BY and index expressions elsewhere.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach (array_reverse($sequence->productions) as $production) {
            if (!in_array($production->rule, ['collate', 'sortorder'], true)) {
                continue;
            }
            $range = $sequence->range($production->id);
            if ($range !== null && $sequence->terminals[$range[0]]->within('eidlist')) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'sqlite.identifier-list');
            }
        }
        return $sequence;
    }
}
