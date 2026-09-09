<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Generation\Token\UniqueOptionRule;
use SqlFaker\MySql\Generation\Rewrite\Alter\OrderByRule;
use SqlFaker\MySql\Generation\Rewrite\Replication\StartRule;

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
            new UniqueOptionRule('start', 'start_transaction_option', ['READ_SYM' => 'access-mode'], ',', 'sql_yacc.yy:start'),
            new SubqueryContextRule(),
            new TransactionCompletionRule(),
            new StartRule(),
            new IntoClauseRule(),
            new ConstraintEnforcementRule(),
            new GeneratedColumnRule(),
            new SetNamesRule(),
            new AlterDatabaseRule(),
            new AlterEventRule(),
            new OrderByRule(),
            new RoleGrantRule(),
            new RequiredAliasRule(),
            new InstanceActionRule(),
            new IntegerContextRule(),
            new LoadSourceCountRule(),
            new FlushExportRule(),
            new ExpressionGroupingRule(['expr', 'bool_pri', 'predicate', 'bit_expr', 'simple_expr'], 'sql_yacc.yy:simple_expr:parenthesized-operands'),
        );
    }
}
