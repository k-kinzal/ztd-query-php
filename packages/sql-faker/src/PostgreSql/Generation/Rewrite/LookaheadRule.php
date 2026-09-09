<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\PgLookahead;

/**
 * Settles parser.c/base_yylex aliases after the grammar's otherwise ambiguous followers have been selected.
 */
final class LookaheadRule implements RewriteRule
{
    /**
     * Records each changed lookahead token instead of silently normalizing it during serialization.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('grant_role_opt') as $id) {
            $name = $sequence->child($id, 'ColLabel');
            $range = $name === null ? null : $sequence->range($name->id);
            if ($range !== null && in_array($sequence->nameAt($range[0] - 1), ['WITH', 'WITH_LA'], true)
                && in_array($sequence->nameAt($range[0]), ['TIME', 'ORDINALITY'], true)) {
                $sequence = $sequence->replace($range[0], 1, [
                    $sequence->terminals[$range[0]]->replaced('IDENT', 'parser.c:WITH-lookahead-before-option-name'),
                ], 'parser.c:WITH-lookahead-before-option-name');
            }
        }
        foreach ($sequence->terminals as $index => $terminal) {
            foreach (PgLookahead::definitions() as $base => $rule) {
                if (!in_array($terminal->name, [$base, $rule['token']], true)) {
                    continue;
                }
                $name = in_array($sequence->nameAt($index + 1), $rule['followed_by'], true) ? $rule['token'] : $base;
                if ($name !== $terminal->name) {
                    $sequence = $sequence->replace($index, 1, [$terminal->replaced($name, 'postgresql.parser-lookahead')], 'postgresql.parser-lookahead');
                }
            }
        }
        return $sequence;
    }
}
