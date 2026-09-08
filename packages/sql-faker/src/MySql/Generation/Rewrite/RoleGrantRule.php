<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use Override;
use SqlFaker\Grammar\Generation\Token\RewriteRule;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

/**
 * sql_yacc.yy/grant and revoke share privilege syntax, but PT_grant_roles requires role identifiers without ON.
 */
final class RoleGrantRule implements RewriteRule
{
    /**
     * Turns static privileges into role names only in a role grant or revoke, retaining host-qualified roles.
     */
    #[Override]
    public function rewrite(TerminalSequence $sequence): TerminalSequence
    {
        foreach ($sequence->occurrences('role_or_privilege') as $id) {
            $range = $sequence->range($id);
            if ($range === null) {
                continue;
            }
            $first = $sequence->terminals[$range[0]];
            $statement = $first->ancestor('grant') ?? $first->ancestor('revoke');
            if ($statement === null || $sequence->child($statement, 'grant_ident') !== null) {
                continue;
            }
            if ($sequence->child($id, 'role_ident_or_text') === null) {
                $sequence = $sequence->replace($range[0], $range[1] - $range[0], [
                    $sequence->insertedFor('IDENT_QUOTED', $id, 'mysql.role-authorization'),
                ], 'mysql.role-authorization');
            } else {
                $columns = $sequence->child($id, 'opt_column_list');
                $columnRange = $columns === null ? null : $sequence->range($columns->id);
                if ($columnRange !== null) {
                    $sequence = $sequence->replace($columnRange[0], $columnRange[1] - $columnRange[0], [], 'mysql.role-columns');
                }
            }
        }
        return $sequence;
    }
}
