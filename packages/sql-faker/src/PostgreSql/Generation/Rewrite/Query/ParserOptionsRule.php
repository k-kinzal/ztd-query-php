<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Query;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Removes options forbidden by the trigger/view grammar actions, scoped to their direct owner.
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L5922-L5936
 * @see https://github.com/postgres/postgres/blob/REL_17_2/src/backend/parser/gram.y#L11204-L11245
 */
final class ParserOptionsRule implements RewriteRule
{
    /**
     * Retains normal triggers and non-recursive view options, including those nested in other statements.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ([['CreateTrigStmt', 'CONSTRAINT', 'opt_or_replace'], ['ViewStmt', 'RECURSIVE', 'opt_check_option']] as [$rule, $marker, $option]) {
            foreach ($sequence->occurrences($rule) as $owner) {
                $direct = array_filter($sequence->terminals, static fn ($terminal): bool =>
                    $terminal->name === $marker && $terminal->ancestor($rule) === $owner && $terminal->rules[count($terminal->rules) - 1] === $rule);
                $child = $sequence->child($owner, $option);
                $range = $child === null ? null : $sequence->range($child->id);
                if ($direct !== [] && $range !== null) {
                    $sequence = $sequence->replace($range[0], $range[1] - $range[0], [], 'gram.y:' . $rule . ':' . $option);
                }
            }
        }
        return $sequence;
    }
}
