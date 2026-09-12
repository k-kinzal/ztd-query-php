<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Generation\Token\RewriteRule;
use SqlFaker\Generation\Token\TerminalSequence;

/**
 * sql/sql_yacc.yy ident_or_empty and alter_database_options compete for an initial option keyword that is also allowed as an identifier.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class AlterDatabaseRule implements RewriteRule
{
    /**
     * Binds the release's DEFAULT token name from sql_yacc.yy.
     */
    public function __construct(private readonly string $defaultTerminal = 'DEFAULT_SYM')
    {
    }

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
            if ($range === null || !in_array($sequence->nameAt($range[0] + 2), ['ENCRYPTION_SYM', 'CHARSET', 'CHAR_SYM', 'COLLATE_SYM'], true)) {
                continue;
            }
            $option = $sequence->terminals[$range[0] + 2];
            $sequence = $sequence->replace($range[0] + 2, 0, [
                $sequence->inserted($this->defaultTerminal, $option, 'mysql.database-option-introducer'),
            ], 'mysql.database-option-introducer');
        }
        return $sequence;
    }
}
