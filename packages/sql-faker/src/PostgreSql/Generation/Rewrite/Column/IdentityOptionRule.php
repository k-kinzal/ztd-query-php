<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite\Column;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * gram.y:alter_identity_column_option excludes AS, RESTART and OWNED BY after SET.
 */
final class IdentityOptionRule implements RewriteRule
{
    /**
     * Uses the dedicated RESTART form and replaces excluded sequence declarations with SET NO CYCLE.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('alter_identity_column_option') as $id) {
            $option = $sequence->child($id, 'SeqOptElem');
            $range = $option === null ? null : $sequence->range($option->id);
            if ($range === null || $sequence->nameAt($range[0] - 1) !== 'SET') {
                continue;
            }
            $name = $sequence->nameAt($range[0]);
            $source = 'gram.y:alter_identity_column_option';
            if ($name === 'RESTART') {
                $sequence = $sequence->replace($range[0] - 1, 1, [], $source);
            } elseif (in_array($name, ['AS', 'OWNED'], true)) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                    $sequence->insertedFor('NO', $option->id, $source),
                    $sequence->insertedFor('CYCLE', $option->id, $source, 1),
                ], $source);
            }
        }
        return $sequence;
    }
}
