<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * Implements gram.y/CopyStmt: PROGRAM needs a filename and COPY TO cannot carry a WHERE clause.
 */
final class CopySourceRule implements RewriteRule
{
    /**
     * Completes only the corresponding COPY source and condition, retaining nested query clauses.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('CopyStmt') as $copy) {
            $program = $sequence->child($copy, 'opt_program');
            $file = $sequence->child($copy, 'copy_file_name');
            if ($program !== null && $file !== null && $sequence->range($program->id) !== null) {
                $range = $sequence->range($file->id);
                if ($range !== null && in_array($sequence->nameAt($range[0]), ['STDIN', 'STDOUT'], true)) {
                    $sequence = $sequence->replace($range[0], 1, [
                        $sequence->terminals[$range[0]]->replaced('SCONST', 'postgresql.copy-program-file'),
                    ], 'postgresql.copy-program-file');
                }
            }
            $direction = $sequence->child($copy, 'copy_from');
            $where = $sequence->child($copy, 'where_clause');
            $range = $direction === null ? null : $sequence->range($direction->id);
            if ($range !== null && $where !== null && $sequence->nameAt($range[0]) === 'TO') {
                $condition = $sequence->range($where->id);
                if ($condition !== null) {
                    $sequence = $sequence->replace($condition[0], $condition[1] - $condition[0], [], 'postgresql.copy-to-where');
                }
            }
        }
        return $sequence;
    }
}
