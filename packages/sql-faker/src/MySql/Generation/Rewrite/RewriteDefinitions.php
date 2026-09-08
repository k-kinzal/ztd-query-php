<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Generation\Token\UniqueOptionRule;

/**
 * Declares structural repairs of MySQL parser diagnostic alternatives and ambiguity.
 */
final class RewriteDefinitions
{
    /**
     * Each rule runs once, before lexical candidates are constructed.
     */
    public function create(): TokenRewriter
    {
        return new TokenRewriter(
            new UniqueOptionRule('require_clause', 'require_list_element', [
                'SUBJECT_SYM' => 'subject', 'ISSUER_SYM' => 'issuer', 'CIPHER_SYM' => 'cipher',
            ], 'AND_SYM', 'sql_yacc.yy:require_list_element'),
            new ConstraintEnforcementRule(),
            new SetNamesRule(),
            new AlterDatabaseRule(),
            new RoleGrantRule(),
            new RequiredAliasRule(),
            new InstanceActionRule(),
            new IntegerContextRule(),
            new LoadSourceCountRule(),
            new FlushExportRule(),
        );
    }
}
