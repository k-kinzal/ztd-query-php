<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Symbol;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;
use SqlFaker\MySqlProvider;

/**
 * Grammar-driven SQL generator for MySQL.
 *
 * This class generates syntactically valid SQL strings using MySQL's official grammar.
 * It implements formal grammar derivation: starting from a non-terminal symbol,
 * repeatedly replacing non-terminals with production rule right-hand sides
 * until only terminal symbols remain.
 */
final class SqlGenerator
{
    private const DERIVATION_LIMIT = 5000;
    private const TABLE_VALUE_ARITY_LIMIT = 8;

    private Grammar $grammar;
    private FakerGenerator $faker;
    private MySqlProvider $provider;
    private TerminationAnalyzer $terminationAnalyzer;
    private RandomStringGenerator $rsg;

    private int $targetDepth = PHP_INT_MAX;
    private int $derivationSteps = 0;
    private int $identifierOrdinal = 0;

    public function __construct(Grammar $grammar, FakerGenerator $faker, MySqlProvider $provider)
    {
        $this->grammar = $this->augmentGrammar($grammar);
        $this->faker = $faker;
        $this->provider = $provider;
        $this->terminationAnalyzer = new TerminationAnalyzer($this->grammar);
        $this->rsg = new RandomStringGenerator($faker);
    }

    private function augmentGrammar(Grammar $grammar): Grammar
    {
        $ruleMap = $grammar->ruleMap;

        $ruleMap = $this->keepSingleNonTerminalAlternatives($ruleMap, 'ident', ['IDENT_sys']);
        $ruleMap = $this->keepSingleNonTerminalAlternatives($ruleMap, 'label_ident', ['IDENT_sys']);
        $ruleMap = $this->keepSingleNonTerminalAlternatives($ruleMap, 'role_ident', ['IDENT_sys']);
        $ruleMap = $this->keepSingleNonTerminalAlternatives($ruleMap, 'lvalue_ident', ['IDENT_sys']);
        $ruleMap = $this->augmentUserRule($ruleMap);
        $ruleMap = $this->augmentAlterEventRule($ruleMap);
        $ruleMap = $this->augmentAlterInstanceRule($ruleMap);
        $ruleMap = $this->augmentCommitRule($ruleMap);
        $ruleMap = $this->augmentRollbackRule($ruleMap);
        $ruleMap = $this->augmentBoolPriRule($ruleMap);
        $ruleMap = $this->augmentStartTransactionRule($ruleMap);
        $ruleMap = $this->augmentGrantRule($ruleMap);
        $ruleMap = $this->augmentRevokeRule($ruleMap);
        $ruleMap = $this->augmentCloneRule($ruleMap);
        $ruleMap = $this->augmentTableValueConstructorRule($ruleMap);
        $ruleMap = $this->augmentSignalRule($ruleMap);
        $ruleMap = $this->augmentLimitRules($ruleMap);
        $ruleMap = $this->augmentAlterDatabaseRule($ruleMap);
        $ruleMap = $this->augmentCharsetRules($ruleMap);
        $ruleMap = $this->augmentSetRule($ruleMap);
        $ruleMap = $this->augmentReplicationRules($ruleMap);
        $ruleMap = $this->augmentSignalInformationRules($ruleMap);
        $ruleMap = $this->augmentResetRule($ruleMap);
        $ruleMap = $this->augmentFlushRule($ruleMap);
        $ruleMap = $this->augmentSrsRules($ruleMap);
        $ruleMap = $this->augmentUndoTablespaceRules($ruleMap);
        $ruleMap = $this->augmentDiagnosticsRule($ruleMap);
        $ruleMap = $this->augmentExplainRule($ruleMap);
        $ruleMap = $this->augmentResourceGroupRule($ruleMap);
        $ruleMap = $this->augmentAlterUserRule($ruleMap);

        return new Grammar($grammar->startSymbol, $ruleMap);
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @param list<string> $allowedNonTerminals
     * @return array<string, ProductionRule>
     */
    private function keepSingleNonTerminalAlternatives(array $ruleMap, string $ruleName, array $allowedNonTerminals): array
    {
        $rule = $ruleMap[$ruleName] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $rule->alternatives,
            static function (Production $alt) use ($allowedNonTerminals): bool {
                if (count($alt->symbols) !== 1) {
                    return false;
                }

                $symbol = $alt->symbols[0];

                return $symbol instanceof NonTerminal
                    && in_array($symbol->value, $allowedNonTerminals, true);
            },
        ));

        if ($filtered !== []) {
            $ruleMap[$ruleName] = new ProductionRule($ruleName, $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentUserRule(array $ruleMap): array
    {
        if (!isset($ruleMap['user'])) {
            return $ruleMap;
        }

        $ruleMap['user'] = new ProductionRule('user', [
            new Production([
                new NonTerminal('TEXT_STRING_sys'),
                new Terminal('@'),
                new NonTerminal('TEXT_STRING_sys'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterEventRule(array $ruleMap): array
    {
        if (!isset($ruleMap['alter_event_stmt'])) {
            return $ruleMap;
        }

        $ruleMap = $this->copyNonEmptyRule($ruleMap, 'ev_alter_on_schedule_completion', 'nonempty_ev_alter_on_schedule_completion');
        $ruleMap = $this->copyNonEmptyRule($ruleMap, 'opt_ev_rename_to', 'nonempty_opt_ev_rename_to');
        $ruleMap = $this->copyNonEmptyRule($ruleMap, 'opt_ev_status', 'nonempty_opt_ev_status');
        $ruleMap = $this->copyNonEmptyRule($ruleMap, 'opt_ev_comment', 'nonempty_opt_ev_comment');
        $ruleMap = $this->copyNonEmptyRule($ruleMap, 'opt_ev_sql_stmt', 'nonempty_opt_ev_sql_stmt');

        $ruleMap['alter_event_stmt'] = new ProductionRule('alter_event_stmt', [
            new Production([
                new Terminal('ALTER'),
                new NonTerminal('definer_opt'),
                new Terminal('EVENT_SYM'),
                new NonTerminal('sp_name'),
                new NonTerminal('nonempty_ev_alter_on_schedule_completion'),
                new NonTerminal('opt_ev_rename_to'),
                new NonTerminal('opt_ev_status'),
                new NonTerminal('opt_ev_comment'),
                new NonTerminal('opt_ev_sql_stmt'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new NonTerminal('definer_opt'),
                new Terminal('EVENT_SYM'),
                new NonTerminal('sp_name'),
                new NonTerminal('ev_alter_on_schedule_completion'),
                new NonTerminal('nonempty_opt_ev_rename_to'),
                new NonTerminal('opt_ev_status'),
                new NonTerminal('opt_ev_comment'),
                new NonTerminal('opt_ev_sql_stmt'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new NonTerminal('definer_opt'),
                new Terminal('EVENT_SYM'),
                new NonTerminal('sp_name'),
                new NonTerminal('ev_alter_on_schedule_completion'),
                new NonTerminal('opt_ev_rename_to'),
                new NonTerminal('nonempty_opt_ev_status'),
                new NonTerminal('opt_ev_comment'),
                new NonTerminal('opt_ev_sql_stmt'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new NonTerminal('definer_opt'),
                new Terminal('EVENT_SYM'),
                new NonTerminal('sp_name'),
                new NonTerminal('ev_alter_on_schedule_completion'),
                new NonTerminal('opt_ev_rename_to'),
                new NonTerminal('opt_ev_status'),
                new NonTerminal('nonempty_opt_ev_comment'),
                new NonTerminal('opt_ev_sql_stmt'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new NonTerminal('definer_opt'),
                new Terminal('EVENT_SYM'),
                new NonTerminal('sp_name'),
                new NonTerminal('ev_alter_on_schedule_completion'),
                new NonTerminal('opt_ev_rename_to'),
                new NonTerminal('opt_ev_status'),
                new NonTerminal('opt_ev_comment'),
                new NonTerminal('nonempty_opt_ev_sql_stmt'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCommitRule(array $ruleMap): array
    {
        if (!isset($ruleMap['commit'])) {
            return $ruleMap;
        }

        $ruleMap['commit'] = new ProductionRule('commit', [
            new Production([new Terminal('COMMIT_SYM'), new NonTerminal('opt_work')]),
            new Production([new Terminal('COMMIT_SYM'), new NonTerminal('opt_work'), new Terminal('RELEASE_SYM')]),
            new Production([new Terminal('COMMIT_SYM'), new NonTerminal('opt_work'), new Terminal('NO_SYM'), new Terminal('RELEASE_SYM')]),
            new Production([new Terminal('COMMIT_SYM'), new NonTerminal('opt_work'), new Terminal('AND_SYM'), new Terminal('NO_SYM'), new Terminal('CHAIN_SYM')]),
            new Production([new Terminal('COMMIT_SYM'), new NonTerminal('opt_work'), new Terminal('AND_SYM'), new Terminal('NO_SYM'), new Terminal('CHAIN_SYM'), new Terminal('RELEASE_SYM')]),
            new Production([new Terminal('COMMIT_SYM'), new NonTerminal('opt_work'), new Terminal('AND_SYM'), new Terminal('NO_SYM'), new Terminal('CHAIN_SYM'), new Terminal('NO_SYM'), new Terminal('RELEASE_SYM')]),
            new Production([new Terminal('COMMIT_SYM'), new NonTerminal('opt_work'), new Terminal('AND_SYM'), new Terminal('CHAIN_SYM')]),
            new Production([new Terminal('COMMIT_SYM'), new NonTerminal('opt_work'), new Terminal('AND_SYM'), new Terminal('CHAIN_SYM'), new Terminal('NO_SYM'), new Terminal('RELEASE_SYM')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentRollbackRule(array $ruleMap): array
    {
        if (!isset($ruleMap['rollback'])) {
            return $ruleMap;
        }

        $ruleMap['rollback'] = new ProductionRule('rollback', [
            new Production([new Terminal('ROLLBACK_SYM'), new NonTerminal('opt_work')]),
            new Production([new Terminal('ROLLBACK_SYM'), new NonTerminal('opt_work'), new Terminal('RELEASE_SYM')]),
            new Production([new Terminal('ROLLBACK_SYM'), new NonTerminal('opt_work'), new Terminal('NO_SYM'), new Terminal('RELEASE_SYM')]),
            new Production([new Terminal('ROLLBACK_SYM'), new NonTerminal('opt_work'), new Terminal('AND_SYM'), new Terminal('CHAIN_SYM')]),
            new Production([new Terminal('ROLLBACK_SYM'), new NonTerminal('opt_work'), new Terminal('AND_SYM'), new Terminal('CHAIN_SYM'), new Terminal('NO_SYM'), new Terminal('RELEASE_SYM')]),
            new Production([new Terminal('ROLLBACK_SYM'), new NonTerminal('opt_work'), new Terminal('AND_SYM'), new Terminal('NO_SYM'), new Terminal('CHAIN_SYM')]),
            new Production([new Terminal('ROLLBACK_SYM'), new NonTerminal('opt_work'), new Terminal('AND_SYM'), new Terminal('NO_SYM'), new Terminal('CHAIN_SYM'), new Terminal('RELEASE_SYM')]),
            new Production([new Terminal('ROLLBACK_SYM'), new NonTerminal('opt_work'), new Terminal('AND_SYM'), new Terminal('NO_SYM'), new Terminal('CHAIN_SYM'), new Terminal('NO_SYM'), new Terminal('RELEASE_SYM')]),
            new Production([new Terminal('ROLLBACK_SYM'), new NonTerminal('opt_work'), new Terminal('TO_SYM'), new NonTerminal('opt_savepoint'), new NonTerminal('ident')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterInstanceRule(array $ruleMap): array
    {
        $rule = $ruleMap['alter_instance_action'] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $rule->alternatives,
            static function (Production $alt): bool {
                $values = array_map(
                    self::symbolValue(...),
                    $alt->symbols,
                );

                if (in_array('ident_or_text', $values, true)) {
                    return false;
                }

                $first = $values[0] ?? null;

                return !in_array($first, ['ENABLE_SYM', 'DISABLE_SYM'], true);
            },
        ));

        if ($filtered !== []) {
            $ruleMap['alter_instance_action'] = new ProductionRule('alter_instance_action', $filtered);
        }

        return $ruleMap;
    }

    private static function symbolValue(Symbol $symbol): string
    {
        return match (true) {
            $symbol instanceof NonTerminal => $symbol->value,
            $symbol instanceof Terminal => $symbol->value,
            default => throw new LogicException('Unexpected symbol type.'),
        };
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentBoolPriRule(array $ruleMap): array
    {
        if (!isset($ruleMap['bool_pri'], $ruleMap['comp_op'])) {
            return $ruleMap;
        }

        $compOpAlternatives = array_values(array_filter(
            $ruleMap['comp_op']->alternatives,
            static function (Production $alt): bool {
                $symbol = $alt->symbols[0] ?? null;

                return !$symbol instanceof Terminal || $symbol->value !== 'EQUAL_SYM';
            },
        ));

        if ($compOpAlternatives === []) {
            return $ruleMap;
        }

        $ruleMap['comp_op_all_or_any'] = new ProductionRule('comp_op_all_or_any', $compOpAlternatives);
        $ruleMap['bool_pri'] = new ProductionRule('bool_pri', array_map(
            static function (Production $alt): Production {
                $symbols = $alt->symbols;
                if (count($symbols) === 4
                    && $symbols[0] instanceof NonTerminal
                    && $symbols[0]->value === 'bool_pri'
                    && $symbols[1] instanceof NonTerminal
                    && $symbols[1]->value === 'comp_op'
                    && $symbols[2] instanceof NonTerminal
                    && $symbols[2]->value === 'all_or_any'
                    && $symbols[3] instanceof NonTerminal
                    && $symbols[3]->value === 'table_subquery') {
                    $symbols[1] = new NonTerminal('comp_op_all_or_any');
                }

                return new Production($symbols);
            },
            $ruleMap['bool_pri']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentStartTransactionRule(array $ruleMap): array
    {
        if (!isset($ruleMap['start_transaction_option_list'])) {
            return $ruleMap;
        }

        $ruleMap['start_transaction_option_list'] = new ProductionRule('start_transaction_option_list', [
            new Production([new Terminal('WITH'), new Terminal('CONSISTENT_SYM'), new Terminal('SNAPSHOT_SYM')]),
            new Production([new Terminal('READ_SYM'), new Terminal('ONLY_SYM')]),
            new Production([new Terminal('READ_SYM'), new Terminal('WRITE_SYM')]),
            new Production([new Terminal('WITH'), new Terminal('CONSISTENT_SYM'), new Terminal('SNAPSHOT_SYM'), new Terminal(','), new Terminal('READ_SYM'), new Terminal('ONLY_SYM')]),
            new Production([new Terminal('WITH'), new Terminal('CONSISTENT_SYM'), new Terminal('SNAPSHOT_SYM'), new Terminal(','), new Terminal('READ_SYM'), new Terminal('WRITE_SYM')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentGrantRule(array $ruleMap): array
    {
        if (!isset($ruleMap['grant'])) {
            return $ruleMap;
        }

        $ruleMap['granted_role_list'] = new ProductionRule('granted_role_list', [
            new Production([new NonTerminal('role_ident_or_text')]),
            new Production([new NonTerminal('granted_role_list'), new Terminal(','), new NonTerminal('role_ident_or_text')]),
        ]);
        $ruleMap['grant'] = new ProductionRule('grant', [
            new Production([
                new Terminal('GRANT'),
                new NonTerminal('granted_role_list'),
                new Terminal('TO_SYM'),
                new NonTerminal('user_list'),
                new NonTerminal('opt_with_admin_option'),
            ]),
            new Production([
                new Terminal('GRANT'),
                new NonTerminal('role_or_privilege_list'),
                new Terminal('ON_SYM'),
                new NonTerminal('opt_acl_type'),
                new NonTerminal('grant_ident'),
                new Terminal('TO_SYM'),
                new NonTerminal('user_list'),
                new NonTerminal('grant_options'),
                new NonTerminal('opt_grant_as'),
            ]),
            new Production([
                new Terminal('GRANT'),
                new Terminal('ALL'),
                new NonTerminal('opt_privileges'),
                new Terminal('ON_SYM'),
                new NonTerminal('opt_acl_type'),
                new NonTerminal('grant_ident'),
                new Terminal('TO_SYM'),
                new NonTerminal('user_list'),
                new NonTerminal('grant_options'),
                new NonTerminal('opt_grant_as'),
            ]),
            new Production([
                new Terminal('GRANT'),
                new Terminal('PROXY_SYM'),
                new Terminal('ON_SYM'),
                new NonTerminal('user'),
                new Terminal('TO_SYM'),
                new NonTerminal('user_list'),
                new NonTerminal('opt_grant_option'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentRevokeRule(array $ruleMap): array
    {
        if (!isset($ruleMap['revoke'])) {
            return $ruleMap;
        }

        $ruleMap['revoked_role_list'] = new ProductionRule('revoked_role_list', [
            new Production([new NonTerminal('role_ident_or_text')]),
            new Production([new NonTerminal('revoked_role_list'), new Terminal(','), new NonTerminal('role_ident_or_text')]),
        ]);
        $ruleMap['revoke'] = new ProductionRule('revoke', [
            new Production([
                new Terminal('REVOKE'),
                new NonTerminal('if_exists'),
                new NonTerminal('revoked_role_list'),
                new Terminal('FROM'),
                new NonTerminal('user_list'),
                new NonTerminal('opt_ignore_unknown_user'),
            ]),
            new Production([
                new Terminal('REVOKE'),
                new NonTerminal('role_or_privilege_list'),
                new Terminal('ON_SYM'),
                new NonTerminal('opt_acl_type'),
                new NonTerminal('grant_ident'),
                new Terminal('FROM'),
                new NonTerminal('user_list'),
                new NonTerminal('opt_ignore_unknown_user'),
            ]),
            new Production([
                new Terminal('REVOKE'),
                new Terminal('ALL'),
                new NonTerminal('opt_privileges'),
                new Terminal('ON_SYM'),
                new NonTerminal('opt_acl_type'),
                new NonTerminal('grant_ident'),
                new Terminal('FROM'),
                new NonTerminal('user_list'),
                new NonTerminal('opt_ignore_unknown_user'),
            ]),
            new Production([
                new Terminal('REVOKE'),
                new Terminal('ALL'),
                new NonTerminal('opt_privileges'),
                new Terminal(','),
                new Terminal('GRANT'),
                new Terminal('OPTION'),
                new Terminal('FROM'),
                new NonTerminal('user_list'),
                new NonTerminal('opt_ignore_unknown_user'),
            ]),
            new Production([
                new Terminal('REVOKE'),
                new Terminal('PROXY_SYM'),
                new Terminal('ON_SYM'),
                new NonTerminal('user'),
                new Terminal('FROM'),
                new NonTerminal('user_list'),
                new NonTerminal('opt_ignore_unknown_user'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCloneRule(array $ruleMap): array
    {
        if (!isset($ruleMap['clone_stmt'])) {
            return $ruleMap;
        }

        $ruleMap['clone_stmt'] = new ProductionRule('clone_stmt', [
            new Production([
                new Terminal('CLONE_SYM'),
                new Terminal('LOCAL_SYM'),
                new Terminal('DATA_SYM'),
                new Terminal('DIRECTORY_SYM'),
                new NonTerminal('opt_equal'),
                new NonTerminal('TEXT_STRING_filesystem'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentTableValueConstructorRule(array $ruleMap): array
    {
        if (!isset($ruleMap['table_value_constructor'])) {
            return $ruleMap;
        }

        $ruleMap['table_value_expr'] = new ProductionRule('table_value_expr', [
            new Production([new NonTerminal('signed_literal')]),
            new Production([new NonTerminal('null_as_literal')]),
        ]);

        $constructorAlternatives = [];
        for ($arity = 1; $arity <= self::TABLE_VALUE_ARITY_LIMIT; $arity++) {
            $valuesRule = sprintf('table_value_values_%d', $arity);
            $rowRule = sprintf('table_value_row_value_explicit_%d', $arity);
            $rowListRule = sprintf('table_value_values_row_list_%d', $arity);
            $constructorRule = sprintf('table_value_constructor_%d', $arity);

            $valueSymbols = [];
            for ($index = 0; $index < $arity; $index++) {
                if ($index > 0) {
                    $valueSymbols[] = new Terminal(',');
                }
                $valueSymbols[] = new NonTerminal('table_value_expr');
            }

            $ruleMap[$valuesRule] = new ProductionRule($valuesRule, [
                new Production($valueSymbols),
            ]);
            $ruleMap[$rowRule] = new ProductionRule($rowRule, [
                new Production([
                    new Terminal('ROW_SYM'),
                    new Terminal('('),
                    new NonTerminal($valuesRule),
                    new Terminal(')'),
                ]),
            ]);
            $ruleMap[$rowListRule] = new ProductionRule($rowListRule, [
                new Production([new NonTerminal($rowRule)]),
                new Production([
                    new NonTerminal($rowListRule),
                    new Terminal(','),
                    new NonTerminal($rowRule),
                ]),
            ]);
            $ruleMap[$constructorRule] = new ProductionRule($constructorRule, [
                new Production([
                    new Terminal('VALUES'),
                    new NonTerminal($rowListRule),
                ]),
            ]);

            $constructorAlternatives[] = new Production([new NonTerminal($constructorRule)]);
        }

        $ruleMap['table_value_constructor'] = new ProductionRule('table_value_constructor', $constructorAlternatives);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentSignalRule(array $ruleMap): array
    {
        if (!isset($ruleMap['sqlstate'])) {
            return $ruleMap;
        }

        $ruleMap['safe_sqlstate_literal'] = new ProductionRule('safe_sqlstate_literal', [
            new Production([new Terminal("'45000'")]),
            new Production([new Terminal("'01000'")]),
        ]);
        $ruleMap['sqlstate'] = new ProductionRule('sqlstate', [
            new Production([
                new Terminal('SQLSTATE_SYM'),
                new NonTerminal('opt_value'),
                new NonTerminal('safe_sqlstate_literal'),
            ]),
        ]);
        $ruleMap['signal_sqlstate_stmt'] = new ProductionRule('signal_sqlstate_stmt', [
            new Production([
                new Terminal('SIGNAL_SYM'),
                new NonTerminal('sqlstate'),
                new NonTerminal('opt_set_signal_information'),
            ]),
        ]);

        if (isset($ruleMap['signal_stmt'])) {
            $ruleMap['signal_stmt'] = new ProductionRule('signal_stmt', [
                new Production([new NonTerminal('signal_sqlstate_stmt')]),
                ...$ruleMap['signal_stmt']->alternatives,
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterDatabaseRule(array $ruleMap): array
    {
        if (!isset($ruleMap['alter_database_option'])) {
            return $ruleMap;
        }

        $ruleMap['boolean_numeric_option'] = new ProductionRule('boolean_numeric_option', [
            new Production([new Terminal('0')]),
            new Production([new Terminal('1')]),
        ]);
        $ruleMap['safe_encryption_literal'] = new ProductionRule('safe_encryption_literal', [
            new Production([new Terminal("'Y'")]),
            new Production([new Terminal("'N'")]),
        ]);

        $ruleMap['alter_database_option'] = new ProductionRule('alter_database_option', array_map(
            static function (Production $alt): Production {
                if (count($alt->symbols) === 4
                    && $alt->symbols[0] instanceof Terminal
                    && $alt->symbols[0]->value === 'READ_SYM'
                    && $alt->symbols[1] instanceof Terminal
                    && $alt->symbols[1]->value === 'ONLY_SYM') {
                    return new Production([
                        new Terminal('READ_SYM'),
                        new Terminal('ONLY_SYM'),
                        new NonTerminal('opt_equal'),
                        new NonTerminal('read_only_option_value'),
                    ]);
                }

                return $alt;
            },
            $ruleMap['alter_database_option']->alternatives,
        ));
        $ruleMap['read_only_option_value'] = new ProductionRule('read_only_option_value', [
            new Production([new NonTerminal('boolean_numeric_option')]),
            new Production([new Terminal('DEFAULT_SYM')]),
        ]);
        $ruleMap['create_database_option_non_encryption'] = new ProductionRule('create_database_option_non_encryption', [
            new Production([new NonTerminal('default_collation')]),
            new Production([new NonTerminal('default_charset')]),
        ]);
        $ruleMap['alter_database_option_non_encryption'] = new ProductionRule('alter_database_option_non_encryption', [
            new Production([new NonTerminal('create_database_option_non_encryption')]),
            new Production([
                new Terminal('READ_SYM'),
                new Terminal('ONLY_SYM'),
                new NonTerminal('opt_equal'),
                new NonTerminal('read_only_option_value'),
            ]),
        ]);
        $ruleMap['alter_database_option_non_encryption_list'] = new ProductionRule('alter_database_option_non_encryption_list', [
            new Production([new NonTerminal('alter_database_option_non_encryption')]),
            new Production([
                new NonTerminal('alter_database_option_non_encryption_list'),
                new NonTerminal('alter_database_option_non_encryption'),
            ]),
        ]);
        $ruleMap['alter_database_options_with_encryption'] = new ProductionRule('alter_database_options_with_encryption', [
            new Production([new NonTerminal('default_encryption')]),
            new Production([
                new NonTerminal('default_encryption'),
                new NonTerminal('alter_database_option_non_encryption_list'),
            ]),
            new Production([
                new NonTerminal('alter_database_option_non_encryption_list'),
                new NonTerminal('default_encryption'),
            ]),
            new Production([
                new NonTerminal('alter_database_option_non_encryption_list'),
                new NonTerminal('default_encryption'),
                new NonTerminal('alter_database_option_non_encryption_list'),
            ]),
        ]);
        $ruleMap['alter_database_encryption_stmt'] = new ProductionRule('alter_database_encryption_stmt', [
            new Production([
                new Terminal('ALTER'),
                new Terminal('DATABASE'),
                new NonTerminal('ident'),
                new NonTerminal('alter_database_options_with_encryption'),
            ]),
        ]);

        foreach (['default_encryption', 'ts_option_encryption', 'create_table_option'] as $ruleName) {
            if (!isset($ruleMap[$ruleName])) {
                continue;
            }

            $ruleMap[$ruleName] = new ProductionRule($ruleName, array_map(
                static function (Production $alt): Production {
                    $symbols = $alt->symbols;
                    foreach ($symbols as $index => $symbol) {
                        if ($symbol instanceof NonTerminal && $symbol->value === 'TEXT_STRING_sys') {
                            $symbols[$index] = new NonTerminal('safe_encryption_literal');
                        }
                    }

                    return new Production($symbols);
                },
                $ruleMap[$ruleName]->alternatives,
            ));
        }

        if (isset($ruleMap['alter_database_stmt'])) {
            $ruleMap['alter_database_stmt'] = new ProductionRule('alter_database_stmt', array_map(
                static function (Production $alt): Production {
                    $symbols = $alt->symbols;
                    foreach ($symbols as $index => $symbol) {
                        if ($symbol instanceof NonTerminal && $symbol->value === 'ident_or_empty') {
                            $symbols[$index] = new NonTerminal('ident');
                        }
                    }

                    return new Production($symbols);
                },
                $ruleMap['alter_database_stmt']->alternatives,
            ));
            $ruleMap['alter_database_stmt'] = new ProductionRule('alter_database_stmt', [
                new Production([new NonTerminal('alter_database_encryption_stmt')]),
                ...$ruleMap['alter_database_stmt']->alternatives,
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentLimitRules(array $ruleMap): array
    {
        if (!isset($ruleMap['limit_option'])) {
            return $ruleMap;
        }

        $ruleMap['safe_limit_literal'] = new ProductionRule('safe_limit_literal', [
            new Production([new Terminal('0')]),
            new Production([new Terminal('1')]),
            new Production([new Terminal('2')]),
            new Production([new Terminal('10')]),
            new Production([new Terminal('100')]),
        ]);
        $ruleMap['limit_option'] = new ProductionRule('limit_option', [
            new Production([new NonTerminal('safe_limit_literal')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCharsetRules(array $ruleMap): array
    {
        foreach (['charset_name', 'old_or_new_charset_name'] as $ruleName) {
            if (isset($ruleMap[$ruleName])) {
                $ruleMap[$ruleName] = new ProductionRule($ruleName, [
                    new Production([new Terminal('utf8mb4')]),
                    new Production([new Terminal('BINARY_SYM')]),
                ]);
            }
        }

        if (isset($ruleMap['collation_name'])) {
            $ruleMap['collation_name'] = new ProductionRule('collation_name', [
                new Production([new Terminal('utf8mb4_0900_ai_ci')]),
                new Production([new Terminal('BINARY_SYM')]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentSetRule(array $ruleMap): array
    {
        $rule = $ruleMap['option_value_no_option_type'] ?? null;
        if ($rule === null || !isset($ruleMap['option_value_following_option_type'], $ruleMap['set'])) {
            return $ruleMap;
        }

        $ruleMap['safe_option_type'] = new ProductionRule('safe_option_type', [
            new Production([new Terminal('GLOBAL_SYM')]),
            new Production([new Terminal('LOCAL_SYM')]),
            new Production([new Terminal('SESSION_SYM')]),
        ]);
        $ruleMap['safe_sql_mode_literal'] = new ProductionRule('safe_sql_mode_literal', [
            new Production([new Terminal("''")]),
        ]);
        $ruleMap['safe_set_system_assignment'] = new ProductionRule('safe_set_system_assignment', [
            new Production([
                new Terminal('autocommit'),
                new NonTerminal('equal'),
                new NonTerminal('boolean_numeric_option'),
            ]),
            new Production([
                new Terminal('sql_mode'),
                new NonTerminal('equal'),
                new NonTerminal('safe_sql_mode_literal'),
            ]),
        ]);
        $ruleMap['safe_set_system_assignment_with_atat'] = new ProductionRule('safe_set_system_assignment_with_atat', [
            new Production([
                new Terminal('@'),
                new Terminal('@'),
                new NonTerminal('opt_set_var_ident_type'),
                new Terminal('autocommit'),
                new NonTerminal('equal'),
                new NonTerminal('boolean_numeric_option'),
            ]),
            new Production([
                new Terminal('@'),
                new Terminal('@'),
                new NonTerminal('opt_set_var_ident_type'),
                new Terminal('sql_mode'),
                new NonTerminal('equal'),
                new NonTerminal('safe_sql_mode_literal'),
            ]),
        ]);
        $ruleMap['option_type'] = new ProductionRule('option_type', $ruleMap['safe_option_type']->alternatives);
        $ruleMap['option_value_following_option_type'] = new ProductionRule('option_value_following_option_type', [
            new Production([new NonTerminal('safe_set_system_assignment')]),
        ]);

        $filtered = array_values(array_filter(
            $rule->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    self::symbolValue(...),
                    $alt->symbols,
                );

                if (count($names) === 3
                    && $names[0] === 'lvalue_variable'
                    && $names[1] === 'equal'
                    && $names[2] === 'set_expr_or_default') {
                    return false;
                }

                if (count($names) === 6
                    && $names[0] === '@'
                    && $names[1] === '@'
                    && $names[2] === 'opt_set_var_ident_type'
                    && $names[3] === 'lvalue_variable'
                    && $names[4] === 'equal'
                    && $names[5] === 'set_expr_or_default') {
                    return false;
                }

                return !(count($alt->symbols) === 3
                    && $alt->symbols[0] instanceof Terminal
                    && $alt->symbols[0]->value === 'NAMES_SYM'
                    && $alt->symbols[2] instanceof NonTerminal
                    && $alt->symbols[2]->value === 'expr');
            },
        ));

        if ($filtered !== []) {
            $ruleMap['option_value_no_option_type'] = new ProductionRule('option_value_no_option_type', [
                new Production([new NonTerminal('safe_set_system_assignment')]),
                new Production([new NonTerminal('safe_set_system_assignment_with_atat')]),
                ...$filtered,
            ]);
        }

        $ruleMap['set_system_variable_stmt'] = new ProductionRule('set_system_variable_stmt', [
            new Production([
                new Terminal('SET_SYM'),
                new NonTerminal('safe_set_system_assignment'),
            ]),
            new Production([
                new Terminal('SET_SYM'),
                new NonTerminal('safe_option_type'),
                new NonTerminal('safe_set_system_assignment'),
            ]),
            new Production([
                new Terminal('SET_SYM'),
                new NonTerminal('safe_set_system_assignment_with_atat'),
            ]),
        ]);
        $ruleMap['set'] = new ProductionRule('set', [
            new Production([new NonTerminal('set_system_variable_stmt')]),
            ...$ruleMap['set']->alternatives,
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentReplicationRules(array $ruleMap): array
    {
        if (!isset($ruleMap['source_def'])) {
            return $ruleMap;
        }

        if (isset($ruleMap['source_defs'])) {
            $ruleMap['source_defs'] = new ProductionRule('source_defs', [
                new Production([new NonTerminal('source_def')]),
            ]);
        }

        $booleanOptionTerminals = [
            'SOURCE_SSL_SYM',
            'SOURCE_SSL_VERIFY_SERVER_CERT_SYM',
            'GET_SOURCE_PUBLIC_KEY_SYM',
            'SOURCE_AUTO_POSITION_SYM',
            'REQUIRE_ROW_FORMAT_SYM',
            'SOURCE_CONNECTION_AUTO_FAILOVER_SYM',
            'GTID_ONLY_SYM',
        ];

        $ruleMap['safe_replication_text_option'] = new ProductionRule('safe_replication_text_option', [
            new Production([new Terminal('SOURCE_HOST_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('NETWORK_NAMESPACE_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_BIND_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_USER_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_PASSWORD_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_SSL_CA_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_SSL_CAPATH_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_TLS_VERSION_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_SSL_CERT_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_SSL_CIPHER_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_SSL_KEY_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_SSL_CRL_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_SSL_CRLPATH_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
            new Production([new Terminal('SOURCE_PUBLIC_KEY_PATH_SYM'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
        ]);
        $ruleMap['safe_replication_numeric_option'] = new ProductionRule('safe_replication_numeric_option', [
            new Production([new Terminal('SOURCE_PORT_SYM'), new Terminal('EQ'), new NonTerminal('ulong_num')]),
            new Production([new Terminal('SOURCE_CONNECT_RETRY_SYM'), new Terminal('EQ'), new NonTerminal('ulong_num')]),
            new Production([new Terminal('SOURCE_RETRY_COUNT_SYM'), new Terminal('EQ'), new NonTerminal('ulong_num')]),
            new Production([new Terminal('SOURCE_DELAY_SYM'), new Terminal('EQ'), new NonTerminal('ulong_num')]),
            new Production([new Terminal('SOURCE_HEARTBEAT_PERIOD_SYM'), new Terminal('EQ'), new NonTerminal('NUM_literal')]),
            new Production([new Terminal('SOURCE_ZSTD_COMPRESSION_LEVEL_SYM'), new Terminal('EQ'), new NonTerminal('ulong_num')]),
        ]);

        $ruleMap['replication_filter_pattern'] = new ProductionRule('replication_filter_pattern', [
            new Production([new Terminal('FILTER_DB_TABLE_PATTERN')]),
        ]);
        $ruleMap['filter_string'] = new ProductionRule('filter_string', [
            new Production([new NonTerminal('replication_filter_pattern')]),
        ]);

        $ruleMap['source_def'] = new ProductionRule('source_def', [
            new Production([new NonTerminal('safe_replication_text_option')]),
            new Production([new NonTerminal('safe_replication_numeric_option')]),
            ...array_map(
                static fn (string $token): Production => new Production([
                    new Terminal($token),
                    new Terminal('EQ'),
                    new NonTerminal('boolean_numeric_option'),
                ]),
                $booleanOptionTerminals,
            ),
        ]);

        if (isset($ruleMap['change_replication_stmt'])) {
            $ruleMap['change_replication_stmt'] = new ProductionRule('change_replication_stmt', [
                new Production([
                    new Terminal('CHANGE'),
                    new Terminal('REPLICATION'),
                    new Terminal('SOURCE_SYM'),
                    new Terminal('TO_SYM'),
                    new NonTerminal('source_defs'),
                    new NonTerminal('opt_channel'),
                ]),
            ]);
        }

        if (isset($ruleMap['replica_until'])) {
            $ruleMap['replica_until'] = new ProductionRule('replica_until', [
                new Production([
                    new Terminal('SOURCE_LOG_FILE_SYM'),
                    new Terminal('EQ'),
                    new NonTerminal('TEXT_STRING_sys_nonewline'),
                    new Terminal(','),
                    new Terminal('SOURCE_LOG_POS_SYM'),
                    new Terminal('EQ'),
                    new NonTerminal('ulonglong_num'),
                ]),
                new Production([
                    new Terminal('RELAY_LOG_FILE_SYM'),
                    new Terminal('EQ'),
                    new NonTerminal('TEXT_STRING_sys_nonewline'),
                    new Terminal(','),
                    new Terminal('RELAY_LOG_POS_SYM'),
                    new Terminal('EQ'),
                    new NonTerminal('ulong_num'),
                ]),
                new Production([new Terminal('SQL_BEFORE_GTIDS'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys')]),
                new Production([new Terminal('SQL_AFTER_GTIDS'), new Terminal('EQ'), new NonTerminal('TEXT_STRING_sys')]),
                new Production([new Terminal('SQL_AFTER_MTS_GAPS')]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentSignalInformationRules(array $ruleMap): array
    {
        if (!isset($ruleMap['opt_set_signal_information'])) {
            return $ruleMap;
        }

        $tokens = [
            'CLASS_ORIGIN_SYM',
            'SUBCLASS_ORIGIN_SYM',
            'CONSTRAINT_CATALOG_SYM',
            'CONSTRAINT_SCHEMA_SYM',
            'CONSTRAINT_NAME_SYM',
            'CATALOG_NAME_SYM',
            'SCHEMA_NAME_SYM',
            'TABLE_NAME_SYM',
            'COLUMN_NAME_SYM',
            'CURSOR_NAME_SYM',
            'MESSAGE_TEXT_SYM',
            'MYSQL_ERRNO_SYM',
        ];
        foreach ($tokens as $index => $token) {
            $ruleMap[sprintf('safe_signal_info_item_%d', $index)] = new ProductionRule(sprintf('safe_signal_info_item_%d', $index), [
                new Production([
                    new Terminal($token),
                    new Terminal('EQ'),
                    new NonTerminal('signal_allowed_expr'),
                ]),
            ]);
        }

        $count = count($tokens);
        for ($start = $count; $start >= 0; $start--) {
            $ruleName = sprintf('safe_signal_info_suffix_%d', $start);
            $alternatives = [new Production([])];
            for ($index = $start; $index < $count; $index++) {
                $alternatives[] = new Production([
                    new Terminal(','),
                    new NonTerminal(sprintf('safe_signal_info_item_%d', $index)),
                    new NonTerminal(sprintf('safe_signal_info_suffix_%d', $index + 1)),
                ]);
            }
            $ruleMap[$ruleName] = new ProductionRule($ruleName, $alternatives);
        }

        $listAlternatives = [];
        for ($index = 0; $index < $count; $index++) {
            $listAlternatives[] = new Production([
                new NonTerminal(sprintf('safe_signal_info_item_%d', $index)),
                new NonTerminal(sprintf('safe_signal_info_suffix_%d', $index + 1)),
            ]);
        }
        $ruleMap['safe_signal_information_item_list'] = new ProductionRule('safe_signal_information_item_list', $listAlternatives);
        $ruleMap['opt_set_signal_information'] = new ProductionRule('opt_set_signal_information', [
            new Production([]),
            new Production([
                new Terminal('SET_SYM'),
                new NonTerminal('safe_signal_information_item_list'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentResetRule(array $ruleMap): array
    {
        if (!isset($ruleMap['source_reset_options'])) {
            return $ruleMap;
        }

        $ruleMap['source_reset_options'] = new ProductionRule('source_reset_options', [
            new Production([]),
            new Production([
                new Terminal('TO_SYM'),
                new Terminal('RESET_MASTER_INDEX'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentFlushRule(array $ruleMap): array
    {
        if (isset($ruleMap['opt_no_write_to_binlog'])) {
            $ruleMap['opt_no_write_to_binlog'] = new ProductionRule('opt_no_write_to_binlog', [
                new Production([]),
                new Production([new Terminal('NO_WRITE_TO_BINLOG')]),
            ]);
        }

        $hasFlushRules = isset($ruleMap['flush_options'], $ruleMap['flush_options_list']);
        if (!$hasFlushRules) {
            return $ruleMap;
        }

        $ruleMap['safe_flush_option'] = new ProductionRule('safe_flush_option', [
            new Production([new Terminal('ERROR_SYM'), new Terminal('LOGS_SYM')]),
            new Production([new Terminal('ENGINE_SYM'), new Terminal('LOGS_SYM')]),
            new Production([new Terminal('GENERAL'), new Terminal('LOGS_SYM')]),
            new Production([new Terminal('SLOW'), new Terminal('LOGS_SYM')]),
            new Production([new Terminal('BINARY_SYM'), new Terminal('LOGS_SYM')]),
            new Production([new Terminal('RELAY'), new Terminal('LOGS_SYM'), new NonTerminal('opt_channel')]),
            new Production([new Terminal('HOSTS_SYM')]),
            new Production([new Terminal('PRIVILEGES')]),
            new Production([new Terminal('LOGS_SYM')]),
            new Production([new Terminal('STATUS_SYM')]),
            new Production([new Terminal('OPTIMIZER_COSTS_SYM')]),
        ]);
        $ruleMap['flush_options_list'] = new ProductionRule('flush_options_list', [
            new Production([new NonTerminal('safe_flush_option')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentSrsRules(array $ruleMap): array
    {
        if (!isset($ruleMap['real_ulonglong_num'])) {
            return $ruleMap;
        }

        $ruleMap['srs_numeric_id'] = new ProductionRule('srs_numeric_id', [
            new Production([new Terminal('1')]),
            new Production([new Terminal('999999')]),
        ]);
        $ruleMap['safe_srs_definition_literal'] = new ProductionRule('safe_srs_definition_literal', [
            new Production([new Terminal('\'GEOGCS["WGS 84",DATUM["World Geodetic System 1984",SPHEROID["WGS 84",6378137,298.257223563]],PRIMEM["Greenwich",0],UNIT["degree",0.017453292519943278]]\'')]),
        ]);
        $ruleMap['srs_name_attribute'] = new ProductionRule('srs_name_attribute', [
            new Production([new Terminal('NAME_SYM'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
        ]);
        $ruleMap['srs_definition_attribute'] = new ProductionRule('srs_definition_attribute', [
            new Production([new Terminal('DEFINITION_SYM'), new NonTerminal('safe_srs_definition_literal')]),
        ]);
        $ruleMap['srs_organization_attribute'] = new ProductionRule('srs_organization_attribute', [
            new Production([
                new Terminal('ORGANIZATION_SYM'),
                new NonTerminal('TEXT_STRING_sys_nonewline'),
                new Terminal('IDENTIFIED_SYM'),
                new Terminal('BY'),
                new NonTerminal('real_ulonglong_num'),
            ]),
        ]);
        $ruleMap['srs_description_attribute'] = new ProductionRule('srs_description_attribute', [
            new Production([new Terminal('DESCRIPTION_SYM'), new NonTerminal('TEXT_STRING_sys_nonewline')]),
        ]);
        $ruleMap['srs_attributes'] = new ProductionRule('srs_attributes', $this->srsAttributeProductions());

        if (isset($ruleMap['create_srs_stmt'])) {
            $ruleMap['create_srs_stmt'] = new ProductionRule('create_srs_stmt', [
                new Production([
                    new Terminal('CREATE'),
                    new Terminal('OR_SYM'),
                    new Terminal('REPLACE_SYM'),
                    new Terminal('SPATIAL_SYM'),
                    new Terminal('REFERENCE_SYM'),
                    new Terminal('SYSTEM_SYM'),
                    new NonTerminal('srs_numeric_id'),
                    new NonTerminal('srs_attributes'),
                ]),
                new Production([
                    new Terminal('CREATE'),
                    new Terminal('SPATIAL_SYM'),
                    new Terminal('REFERENCE_SYM'),
                    new Terminal('SYSTEM_SYM'),
                    new NonTerminal('opt_if_not_exists'),
                    new NonTerminal('srs_numeric_id'),
                    new NonTerminal('srs_attributes'),
                ]),
            ]);
        }

        if (isset($ruleMap['drop_srs_stmt'])) {
            $ruleMap['drop_srs_stmt'] = new ProductionRule('drop_srs_stmt', [
                new Production([
                    new Terminal('DROP'),
                    new Terminal('SPATIAL_SYM'),
                    new Terminal('REFERENCE_SYM'),
                    new Terminal('SYSTEM_SYM'),
                    new NonTerminal('if_exists'),
                    new NonTerminal('srs_numeric_id'),
                ]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentUndoTablespaceRules(array $ruleMap): array
    {
        if (!isset($ruleMap['opt_undo_tablespace_options'])) {
            return $ruleMap;
        }

        $ruleMap['opt_undo_tablespace_options'] = new ProductionRule('opt_undo_tablespace_options', [
            new Production([]),
            new Production([new NonTerminal('undo_tablespace_option')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentDiagnosticsRule(array $ruleMap): array
    {
        if (!isset($ruleMap['simple_target_specification'])) {
            return $ruleMap;
        }

        $ruleMap['simple_target_specification'] = new ProductionRule('simple_target_specification', [
            new Production([
                new Terminal('@'),
                new NonTerminal('ident_or_text'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentExplainRule(array $ruleMap): array
    {
        if (!isset($ruleMap['opt_explain_format'])) {
            return $ruleMap;
        }

        $ruleMap['safe_explain_format_name'] = new ProductionRule('safe_explain_format_name', [
            new Production([new Terminal('TREE')]),
            new Production([new Terminal('JSON')]),
        ]);
        $ruleMap['opt_explain_format'] = new ProductionRule('opt_explain_format', [
            new Production([]),
            new Production([
                new Terminal('FORMAT_SYM'),
                new Terminal('EQ'),
                new NonTerminal('safe_explain_format_name'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterUserRule(array $ruleMap): array
    {
        if (!isset($ruleMap['factor'])) {
            return $ruleMap;
        }

        $ruleMap['factor'] = new ProductionRule('factor', [
            new Production([new Terminal('2'), new Terminal('FACTOR_SYM')]),
            new Production([new Terminal('3'), new Terminal('FACTOR_SYM')]),
        ]);

        $ruleMap['alter_user_add_two_factors'] = new ProductionRule('alter_user_add_two_factors', [
            new Production([
                new NonTerminal('user'),
                new Terminal('ADD'),
                new Terminal('2'),
                new Terminal('FACTOR_SYM'),
                new NonTerminal('identification'),
                new Terminal('ADD'),
                new Terminal('3'),
                new Terminal('FACTOR_SYM'),
                new NonTerminal('identification'),
            ]),
            new Production([
                new NonTerminal('user'),
                new Terminal('ADD'),
                new Terminal('3'),
                new Terminal('FACTOR_SYM'),
                new NonTerminal('identification'),
                new Terminal('ADD'),
                new Terminal('2'),
                new Terminal('FACTOR_SYM'),
                new NonTerminal('identification'),
            ]),
        ]);
        $ruleMap['alter_user_modify_two_factors'] = new ProductionRule('alter_user_modify_two_factors', [
            new Production([
                new NonTerminal('user'),
                new Terminal('MODIFY_SYM'),
                new Terminal('2'),
                new Terminal('FACTOR_SYM'),
                new NonTerminal('identification'),
                new Terminal('MODIFY_SYM'),
                new Terminal('3'),
                new Terminal('FACTOR_SYM'),
                new NonTerminal('identification'),
            ]),
            new Production([
                new NonTerminal('user'),
                new Terminal('MODIFY_SYM'),
                new Terminal('3'),
                new Terminal('FACTOR_SYM'),
                new NonTerminal('identification'),
                new Terminal('MODIFY_SYM'),
                new Terminal('2'),
                new Terminal('FACTOR_SYM'),
                new NonTerminal('identification'),
            ]),
        ]);
        $ruleMap['alter_user_drop_two_factors'] = new ProductionRule('alter_user_drop_two_factors', [
            new Production([
                new NonTerminal('user'),
                new Terminal('DROP'),
                new Terminal('2'),
                new Terminal('FACTOR_SYM'),
                new Terminal('DROP'),
                new Terminal('3'),
                new Terminal('FACTOR_SYM'),
            ]),
            new Production([
                new NonTerminal('user'),
                new Terminal('DROP'),
                new Terminal('3'),
                new Terminal('FACTOR_SYM'),
                new Terminal('DROP'),
                new Terminal('2'),
                new Terminal('FACTOR_SYM'),
            ]),
        ]);

        if (isset($ruleMap['alter_user'])) {
            $ruleMap['alter_user'] = new ProductionRule('alter_user', array_map(
                static function (Production $alt): Production {
                    $names = array_map(
                        self::symbolValue(...),
                        $alt->symbols,
                    );

                    return match ($names) {
                        ['user', 'ADD', 'factor', 'identification', 'ADD', 'factor', 'identification'] => new Production([new NonTerminal('alter_user_add_two_factors')]),
                        ['user', 'MODIFY_SYM', 'factor', 'identification', 'MODIFY_SYM', 'factor', 'identification'] => new Production([new NonTerminal('alter_user_modify_two_factors')]),
                        ['user', 'DROP', 'factor', 'DROP', 'factor'] => new Production([new NonTerminal('alter_user_drop_two_factors')]),
                        default => $alt,
                    };
                },
                $ruleMap['alter_user']->alternatives,
            ));
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentResourceGroupRule(array $ruleMap): array
    {
        if (isset($ruleMap['vcpu_num_or_range'])) {
            $ruleMap['vcpu_num_or_range'] = new ProductionRule('vcpu_num_or_range', [
                new Production([new Terminal('0')]),
                new Production([new Terminal('0'), new Terminal('-'), new Terminal('1')]),
            ]);
        }

        if (isset($ruleMap['vcpu_range_spec_list'])) {
            $ruleMap['vcpu_range_spec_list'] = new ProductionRule('vcpu_range_spec_list', [
                new Production([new NonTerminal('vcpu_num_or_range')]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @return list<Production>
     */
    private function srsAttributeProductions(): array
    {
        $attributeSets = [
            ['srs_name_attribute', 'srs_definition_attribute'],
            ['srs_name_attribute', 'srs_definition_attribute', 'srs_organization_attribute'],
            ['srs_name_attribute', 'srs_definition_attribute', 'srs_description_attribute'],
            ['srs_name_attribute', 'srs_definition_attribute', 'srs_organization_attribute', 'srs_description_attribute'],
        ];

        $productions = [];
        foreach ($attributeSets as $attributeSet) {
            foreach ($this->permuteRuleNames($attributeSet) as $permutation) {
                $productions[] = new Production(array_map(
                    static fn (string $ruleName): NonTerminal => new NonTerminal($ruleName),
                    $permutation,
                ));
            }
        }

        return $productions;
    }

    /**
     * @param list<string> $items
     * @return list<list<string>>
     */
    private function permuteRuleNames(array $items): array
    {
        if (count($items) <= 1) {
            return [$items];
        }

        $permutations = [];
        foreach ($items as $index => $item) {
            $rest = $items;
            unset($rest[$index]);
            foreach ($this->permuteRuleNames(array_values($rest)) as $permutation) {
                $permutations[] = [$item, ...$permutation];
            }
        }

        return $permutations;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function copyNonEmptyRule(array $ruleMap, string $sourceRule, string $targetRule): array
    {
        $rule = $ruleMap[$sourceRule] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $rule->alternatives,
            static fn (Production $alt): bool => $alt->symbols !== [],
        ));

        if ($filtered !== []) {
            $ruleMap[$targetRule] = new ProductionRule($targetRule, $filtered);
        }

        return $ruleMap;
    }

    /**
     * Generate a syntactically valid SQL string.
     *
     * @param string|null $startRule Grammar rule to start from (null for default)
     * @param int $targetDepth Depth at which generator starts seeking termination (PHP_INT_MAX = unlimited)
     */
    public function generate(?string $startRule = null, int $targetDepth = PHP_INT_MAX): string
    {
        $this->derivationSteps = 0;
        $this->identifierOrdinal = 0;
        $this->targetDepth = max(1, $targetDepth);

        $start = $startRule ?? 'simple_statement_or_begin';

        $terminals = $this->derive($start);

        return $this->render($terminals);
    }

    public function compiledGrammar(): Grammar
    {
        return $this->grammar;
    }

    /**
     * Derivation: repeatedly replace non-terminals with production rule right-hand sides
     * until only terminal symbols remain.
     *
     * @return list<Terminal>
     */
    private function derive(string $startSymbol): array
    {
        /** @var list<Symbol> $form */
        $form = [new NonTerminal($startSymbol)];

        while (true) {
            $index = null;
            foreach ($form as $i => $sym) {
                if ($sym instanceof NonTerminal) {
                    $index = $i;
                    break;
                }
            }

            if ($index === null) {
                break;
            }

            $this->derivationSteps++;
            if ($this->derivationSteps > self::DERIVATION_LIMIT) {
                throw new LogicException('Exceeded derivation limit while generating SQL.');
            }

            /** @var NonTerminal $nonTerminal */
            $nonTerminal = $form[$index];
            $rule = $this->grammar->ruleMap[$nonTerminal->value] ?? throw new LogicException("Unknown grammar rule: {$nonTerminal->value}");
            $alternatives = $rule->alternatives;

            if ($alternatives === []) {
                throw new LogicException('Production rule has no alternatives.');
            }

            if ($this->derivationSteps >= $this->targetDepth) {
                $selectedIndex = 0;
                $bestLength = PHP_INT_MAX;
                foreach ($alternatives as $i => $alt) {
                    $length = $this->terminationAnalyzer->estimateProductionLength($alt);
                    if ($length < $bestLength) {
                        $bestLength = $length;
                        $selectedIndex = $i;
                    }
                }
            } else {
                $selectedIndex = $this->faker->numberBetween(0, count($alternatives) - 1);
            }

            $production = $alternatives[$selectedIndex];

            $form = [
                ...array_slice($form, 0, $index),
                ...$production->symbols,
                ...array_slice($form, $index + 1),
            ];
        }

        /** @var list<Terminal> $form */
        return $form;
    }

    /**
     * Render terminals into an SQL string.
     *
     * This method handles:
     * 1. Terminal resolution: converts Terminal symbols to their string representation
     * 2. Spacing: MySQL's lexer distinguishes some function tokens by requiring
     *    the '(' to follow immediately (e.g. COUNT(*)).
     *
     * @param list<Terminal> $terminals
     */
    private function render(array $terminals): string
    {
        $tokens = [];
        foreach ($terminals as $terminal) {
            $name = $terminal->value;

            $token = match ($name) {
                'END_OF_INPUT' => null,

                'EQ' => '=',
                'EQUAL_SYM' => '<=>',
                'LT' => '<',
                'GT_SYM' => '>',
                'LE' => '<=',
                'GE' => '>=',
                'NE' => '<>',
                'SHIFT_LEFT' => '<<',
                'SHIFT_RIGHT' => '>>',
                'AND_AND_SYM' => '&&',
                'OR2_SYM', 'OR_OR_SYM' => '||',
                'NOT2_SYM' => 'NOT',
                'SET_VAR' => ':=',
                'JSON_SEPARATOR_SYM' => '->',
                'JSON_UNQUOTED_SEPARATOR_SYM' => '->>',
                'NEG' => '-',
                'PARAM_MARKER' => '?',

                'IDENT' => $this->nextCanonicalIdentifier(),
                'IDENT_QUOTED' => '`' . $this->nextCanonicalIdentifier() . '`',
                'TEXT_STRING' => $this->provider->stringLiteral(),
                'NCHAR_STRING' => $this->provider->nationalStringLiteral(),
                'DOLLAR_QUOTED_STRING_SYM' => $this->provider->dollarQuotedString(),
                'NUM' => $this->provider->integerLiteral(),
                'LONG_NUM' => $this->provider->longIntegerLiteral(),
                'ULONGLONG_NUM' => $this->provider->unsignedBigIntLiteral(),
                'DECIMAL_NUM' => $this->provider->decimalLiteral(),
                'FLOAT_NUM' => $this->provider->floatLiteral(),
                'HEX_NUM' => $this->provider->hexLiteral(),
                'BIN_NUM' => $this->provider->binaryLiteral(),
                'LEX_HOSTNAME' => $this->provider->hostname(),
                'FILTER_DB_TABLE_PATTERN' => $this->provider->filterWildcardPattern(),
                'RESET_MASTER_INDEX' => $this->provider->resetMasterIndex(),

                'WITH_ROLLUP_SYM' => 'WITH ROLLUP',

                default => str_ends_with($name, '_SYM')
                    ? substr($name, 0, -4)
                    : $name,
            };

            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        return TokenJoiner::join($tokens, [['@', '*'], ['*', '@'], ['*', ':'], [':', '*']]);
    }

    private function nextCanonicalIdentifier(): string
    {
        return $this->rsg->canonicalIdentifier($this->identifierOrdinal++);
    }
}
