<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use SqlFaker\Grammar\Generation\Token\ExpressionGroupingRule;
use SqlFaker\Grammar\Generation\Token\TokenRewriter;
use SqlFaker\Grammar\Generation\Token\UniqueOptionRule;
use SqlFaker\MySql\Generation\Rewrite\Alter\OrderByRule;
use SqlFaker\MySql\Generation\Rewrite\Expression\QuantifiedComparisonRule;
use SqlFaker\MySql\Generation\Rewrite\Expression\TableValueConstructorRule;
use SqlFaker\MySql\Generation\Rewrite\Partition\FieldListRule;
use SqlFaker\MySql\Generation\Rewrite\Query\JoinGroupingRule;
use SqlFaker\MySql\Generation\Rewrite\Query\QueryContextRule;
use SqlFaker\MySql\Generation\Rewrite\Replication\StartRule;
use SqlFaker\MySql\Generation\Rewrite\Replication\TablePatternRule;

/**
 * Declares structural repairs of MySQL parser diagnostic alternatives and ambiguity.
 */
final class RewriteDefinitions
{
    /**
     * Each rule runs once, before lexical candidates are constructed.
     */
    public function create(string $version = 'mysql-8.4.7'): TokenRewriter
    {
        $defaultTerminal = in_array($version, ['mysql-5.6.51', 'mysql-5.7.44'], true) ? 'DEFAULT' : 'DEFAULT_SYM';
        return new TokenRewriter(
            new UniqueOptionRule('require_clause', 'require_list_element', [
                'SUBJECT_SYM' => 'subject', 'ISSUER_SYM' => 'issuer', 'CIPHER_SYM' => 'cipher',
            ], 'AND_SYM', 'sql_yacc.yy:require_list_element'),
            new UniqueOptionRule('start', 'start_transaction_option', ['READ_SYM' => 'access-mode'], ',', 'sql_yacc.yy:start'),
            new SubqueryContextRule(),
            new QuantifiedComparisonRule(),
            new TableValueConstructorRule(),
            new TransactionCompletionRule(),
            new StartRule(),
            new TablePatternRule(),
            new FieldListRule(),
            new IntoClauseRule(),
            new QueryContextRule(),
            new JoinGroupingRule(),
            new ConstraintEnforcementRule(),
            new GeneratedColumnRule(),
            new SetNamesRule($defaultTerminal),
            new AlterDatabaseRule($defaultTerminal),
            new AlterEventRule(),
            new OrderByRule(),
            new RoleGrantRule(),
            new RequiredAliasRule(),
            new InstanceActionRule(),
            new IntegerContextRule(!in_array($version, ['mysql-5.6.51', 'mysql-5.7.44'], true)),
            new LoadSourceCountRule(),
            new FlushExportRule(),
            new ExpressionGroupingRule(['expr', 'bool_pri', 'predicate', 'bit_expr', 'simple_expr'], 'sql_yacc.yy:simple_expr:parenthesized-operands'),
        );
    }
}
