<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite\Expression;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * Default SQL mode scans || as OR2_SYM; the OR_OR_SYM production constructs Item_func_concat.
 * Preserve its operands and precedence by expressing that production as a native function call.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy#L10368
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc#L920
 */
final class ConcatenationRule implements RewriteRule
{
    /**
     * Rewrites innermost concatenations first, retaining the identities of both operand subtrees.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('simple_expr') as $owner) {
            $range = $sequence->range($owner);
            if ($range === null) {
                continue;
            }
            for ($index = $range[0]; $index < $range[1]; ++$index) {
                $terminal = $sequence->terminals[$index];
                if ($terminal->name !== 'OR_OR_SYM' || ($terminal->ancestors[count($terminal->ancestors) - 1] ?? null) !== $owner) {
                    continue;
                }
                $source = 'sql/sql_yacc.yy:simple_expr:Item_func_concat:default-mode';
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                    $sequence->insertedFor('CONCAT_FUNCTION_NAME', $owner, $source),
                    $sequence->insertedFor('(', $owner, $source, 1),
                    ...array_slice($sequence->terminals, $range[0], $index - $range[0]),
                    $terminal->replaced(',', $source),
                    ...array_slice($sequence->terminals, $index + 1, $range[1] - $index - 1),
                    $sequence->insertedFor(')', $owner, $source, 2),
                ], $source);
                break;
            }
        }
        return $sequence;
    }
}
