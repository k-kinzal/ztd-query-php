<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql/sql_yacc.yy ident_or_empty and alter_database_options compete for an initial ENCRYPTION identifier.
 */
final class AlterDatabaseRule implements RewriteRule
{
    /**
     * Uses the option's explicit DEFAULT introducer when the database name is omitted.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('alter_database_stmt') as $occurrence) {
            $name = $sequence->child($occurrence, 'ident_or_empty');
            if ($name !== null && $sequence->range($name->id) !== null) {
                continue;
            }
            $range = $sequence->range($occurrence);
            if ($range === null || $sequence->nameAt($range[0] + 2) !== 'ENCRYPTION_SYM') {
                continue;
            }
            $option = $sequence->terminals[$range[0] + 2];
            $sequence = $sequence->replace($range[0] + 2, 0, [
                $sequence->inserted('DEFAULT_SYM', $option, 'mysql.database-option-introducer'),
            ], 'mysql.database-option-introducer');
        }
        return $sequence;
    }
}
