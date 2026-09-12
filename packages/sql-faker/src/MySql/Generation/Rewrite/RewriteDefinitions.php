<?php

declare(strict_types=1);

namespace SqlFaker\MySql\Generation\Rewrite;

use SqlFaker\Generation\Token\TerminalMappingRule;
use SqlFaker\Generation\Token\TokenRewriter;
use SqlFaker\MySql\Generation\Rewrite\Alter\OrderByRule;
use SqlFaker\MySql\Generation\Rewrite\Column\AutoIncrementRule;
use SqlFaker\MySql\Generation\Rewrite\Column\FieldLengthRule;
use SqlFaker\MySql\Generation\Rewrite\Expression\ConcatenationRule;
use SqlFaker\MySql\Generation\Rewrite\Expression\ExpressionGroupingRule;
use SqlFaker\MySql\Generation\Rewrite\Expression\QuantifiedComparisonRule;
use SqlFaker\MySql\Generation\Rewrite\Expression\TableValueConstructorRule;
use SqlFaker\MySql\Generation\Rewrite\Name\SystemVariableRule;
use SqlFaker\MySql\Generation\Rewrite\Option\UniqueOptionRule;
use SqlFaker\MySql\Generation\Rewrite\Partition\DefinitionRule;
use SqlFaker\MySql\Generation\Rewrite\Partition\FieldListRule;
use SqlFaker\MySql\Generation\Rewrite\Partition\ListValueRule;
use SqlFaker\MySql\Generation\Rewrite\Partition\ValueArityRule;
use SqlFaker\MySql\Generation\Rewrite\Query\JoinGroupingRule;
use SqlFaker\MySql\Generation\Rewrite\Query\QueryContextRule;
use SqlFaker\MySql\Generation\Rewrite\Query\WindowFrameRule;
use SqlFaker\MySql\Generation\Rewrite\Replication\StartRule;
use SqlFaker\MySql\Generation\Rewrite\Replication\TablePatternRule;
use SqlFaker\MySql\Generation\Rewrite\Routine\LanguageRule;
use SqlFaker\MySql\Generation\Rewrite\Routine\ReturnRule;

/**
 * Declares structural repairs of MySQL parser diagnostic alternatives and ambiguity.
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_lex.cc
 * @see https://github.com/mysql/mysql-server/blob/mysql-8.4.7/sql/sql_yacc.yy
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
            new TerminalMappingRule('not', 'NOT2_SYM', 'NOT_SYM', 'sql/sql_lex.cc:find_keyword:default-mode'),
            new TerminalMappingRule('not2', 'NOT2_SYM', '!', 'sql/sql_lex.cc:find_keyword:default-mode'),
            new ConcatenationRule(),
            new SubqueryContextRule(),
            new QuantifiedComparisonRule(),
            new TableValueConstructorRule(),
            new TransactionCompletionRule(),
            new StartRule(),
            new TablePatternRule(),
            new FieldListRule(),
            new DefinitionRule(),
            new TerminalMappingRule('factor', 'NUM', 'AUTH_FACTOR_NUMBER', 'sql/sql_yacc.yy:factor'),
            new IntoClauseRule(),
            new WindowFrameRule(),
            new QueryContextRule(),
            new JoinGroupingRule(),
            new ConstraintEnforcementRule(),
            new GeneratedColumnRule(),
            new AutoIncrementRule(),
            new SystemVariableRule(),
            new Name\HostNameRule(),
            new SetNamesRule($defaultTerminal),
            new AlterDatabaseRule($defaultTerminal),
            new AlterEventRule(),
            new ReturnRule(),
            new LanguageRule(),
            new OrderByRule(),
            new RoleGrantRule(),
            new RequiredAliasRule(),
            new InstanceActionRule(),
            new IntegerContextRule(!in_array($version, ['mysql-5.6.51', 'mysql-5.7.44'], true)),
            new FieldLengthRule(),
            new LoadSourceCountRule(),
            new FlushExportRule(),
            new ExpressionGroupingRule(['expr', 'bool_pri', 'predicate', 'bit_expr', 'simple_expr', 'part_value_item'], 'sql_yacc.yy:simple_expr:parenthesized-operands'),
            new ListValueRule(),
            new ValueArityRule(),
        );
    }
}
