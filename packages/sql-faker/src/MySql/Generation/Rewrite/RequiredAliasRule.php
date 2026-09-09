<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy marks derived-table and JSON_TABLE aliases optional solely to provide a targeted diagnostic.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
 */
final class RequiredAliasRule implements RewriteRule
{
    /**
     * Completes only missing aliases, before a derived column list if one is present.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach (['derived_table', 'table_function'] as $rule) {
            foreach ($sequence->occurrences($rule) as $id) {
                $alias = $sequence->child($id, 'opt_table_alias');
                if ($alias === null || $sequence->range($alias->id) !== null) {
                    continue;
                }
                $range = $sequence->range($id);
                if ($range === null) {
                    continue;
                }
                $columns = $sequence->child($id, 'opt_derived_column_list');
                $columnRange = $columns === null ? null : $sequence->range($columns->id);
                $sequence = $sequence->replace($columnRange[0] ?? $range[1], 0, [
                    $sequence->insertedFor('AS', $alias->id, 'mysql.required-alias'),
                    $sequence->insertedFor('IDENT_QUOTED', $alias->id, 'mysql.required-alias', 1),
                ], 'mysql.required-alias');
            }
        }
        return $sequence;
    }
}
