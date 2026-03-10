<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use Faker\Generator as FakerGenerator;
use LogicException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\PostgreSql\LexicalValueSource;

/**
 * Grammar-driven SQL generator for PostgreSQL.
 *
 * Generates syntactically valid SQL strings using PostgreSQL's official grammar.
 * It implements formal grammar derivation: starting from a non-terminal symbol,
 * repeatedly replacing non-terminals with production rule right-hand sides
 * until only terminal symbols remain.
 */
final class SqlGenerator
{
    private const DERIVATION_LIMIT = 5000;
    private const CTAS_ARITY_LIMIT = 8;

    private Grammar $grammar;
    private FakerGenerator $faker;
    private LexicalValueSource $lexicalValues;
    private TerminationAnalyzer $terminationAnalyzer;
    private RandomStringGenerator $rsg;

    private int $targetDepth = PHP_INT_MAX;
    private int $derivationSteps = 0;
    private int $identifierOrdinal = 0;

    public function __construct(Grammar $grammar, FakerGenerator $faker, LexicalValueSource $lexicalValues)
    {
        $this->grammar = $this->augmentGrammar($grammar);
        $this->faker = $faker;
        $this->lexicalValues = $lexicalValues;
        $this->terminationAnalyzer = new TerminationAnalyzer($this->grammar);
        $this->rsg = new RandomStringGenerator($faker);
    }

    private function augmentGrammar(Grammar $grammar): Grammar
    {
        $ruleMap = $grammar->ruleMap;

        foreach (['ColId', 'ColLabel', 'type_function_name', 'NonReservedWord'] as $ruleName) {
            $ruleMap = $this->keepSingleTerminalAlternatives($ruleMap, $ruleName, ['IDENT']);
        }

        $ruleMap = $this->augmentQualifiedNames($ruleMap);
        $ruleMap = $this->augmentFunctionNames($ruleMap);
        $ruleMap = $this->filterIndirectionElements($ruleMap);
        $ruleMap = $this->augmentCreatePolicyRule($ruleMap);
        $ruleMap = $this->augmentCreatePartitionOfRule($ruleMap);
        $ruleMap = $this->augmentAlterDatabaseRule($ruleMap);
        $ruleMap = $this->augmentUtilityStatementRules($ruleMap);
        $ruleMap = $this->augmentAlterTableRule($ruleMap);
        $ruleMap = $this->augmentAlterIndexRule($ruleMap);
        $ruleMap = $this->augmentAlterViewRule($ruleMap);
        $ruleMap = $this->augmentAlterSequenceRule($ruleMap);
        $ruleMap = $this->augmentAlterStatisticsRule($ruleMap);
        $ruleMap = $this->augmentAccessMethodRule($ruleMap);
        $ruleMap = $this->augmentAlterDomainRule($ruleMap);
        $ruleMap = $this->augmentAlterTypeRule($ruleMap);
        $ruleMap = $this->augmentAlterEnumRule($ruleMap);
        $ruleMap = $this->augmentAlterRoleRule($ruleMap);
        $ruleMap = $this->augmentAlterRoutineRule($ruleMap);
        $ruleMap = $this->augmentCreateRoleRule($ruleMap);
        $ruleMap = $this->augmentDropRoleRule($ruleMap);
        $ruleMap = $this->augmentFunctionWithArgtypesRule($ruleMap);
        $ruleMap = $this->augmentEventTriggerRule($ruleMap);
        $ruleMap = $this->augmentAnalyzeVacuumRule($ruleMap);
        $ruleMap = $this->augmentOptionListRules($ruleMap);
        $ruleMap = $this->augmentGrantRoleRule($ruleMap);
        $ruleMap = $this->augmentParameterTargetRule($ruleMap);
        $ruleMap = $this->augmentLargeObjectTargetRule($ruleMap);
        $ruleMap = $this->ensureSafeTypeReferenceRules($ruleMap);
        $ruleMap = $this->augmentAlterExtensionContentsRule($ruleMap);
        $ruleMap = $this->augmentPartitionSpecRule($ruleMap);
        $ruleMap = $this->augmentTemporaryRelationCreationRules($ruleMap);
        $ruleMap = $this->filterOperatorDefinitionRule($ruleMap);
        $ruleMap = $this->filterOperatorArgTypesRule($ruleMap);
        $ruleMap = $this->augmentDefineOperatorRule($ruleMap);
        $ruleMap = $this->augmentDefineAggregateRule($ruleMap);
        $ruleMap = $this->filterPublicationObjectSpecRule($ruleMap);
        $ruleMap = $this->augmentPublicationRule($ruleMap);
        $ruleMap = $this->augmentTextSearchTemplateRule($ruleMap);
        $ruleMap = $this->augmentCommentRule($ruleMap);
        $ruleMap = $this->augmentCreateCastRule($ruleMap);
        $ruleMap = $this->augmentDropCastRule($ruleMap);
        $ruleMap = $this->augmentTriggerRule($ruleMap);
        $ruleMap = $this->augmentTargetElementRule($ruleMap);
        $ruleMap = $this->augmentSelectRules($ruleMap);
        $ruleMap = $this->augmentCreateAsRule($ruleMap);
        $ruleMap = $this->augmentViewRule($ruleMap);
        $ruleMap = $this->augmentInsertRule($ruleMap);
        $ruleMap = $this->augmentCteRule($ruleMap);
        $ruleMap = $this->augmentCreateMaterializedViewRule($ruleMap);
        $ruleMap = $this->augmentCreateAssertionRule($ruleMap);
        $ruleMap = $this->augmentMergeRule($ruleMap);
        $ruleMap = $this->augmentRevokeRoleRule($ruleMap);
        $ruleMap = $this->augmentDropTypeRule($ruleMap);
        $ruleMap = $this->augmentCreateFunctionRule($ruleMap);
        $ruleMap = $this->augmentDoStmtRule($ruleMap);

        return new Grammar($grammar->startSymbol, $ruleMap);
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @param list<string> $allowedTerminals
     * @return array<string, ProductionRule>
     */
    private function keepSingleTerminalAlternatives(array $ruleMap, string $ruleName, array $allowedTerminals): array
    {
        $rule = $ruleMap[$ruleName] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $rule->alternatives,
            static function (Production $alt) use ($allowedTerminals): bool {
                if (count($alt->symbols) !== 1) {
                    return false;
                }

                $symbol = $alt->symbols[0];

                return $symbol instanceof Terminal
                    && in_array($symbol->value, $allowedTerminals, true);
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
    private function augmentQualifiedNames(array $ruleMap): array
    {
        if (!isset($ruleMap['qualified_name'], $ruleMap['any_name'])) {
            return $ruleMap;
        }

        $ruleMap['qualified_name'] = new ProductionRule('qualified_name', $this->canonicalQualifiedNameAlternatives('ColId'));
        $ruleMap['any_name'] = new ProductionRule('any_name', $this->canonicalQualifiedNameAlternatives('ColId'));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentFunctionNames(array $ruleMap): array
    {
        if (!isset($ruleMap['func_name'])) {
            return $ruleMap;
        }

        $ruleMap['func_name'] = new ProductionRule('func_name', [
            new Production([new NonTerminal('type_function_name')]),
            ...$this->canonicalQualifiedNameAlternatives('ColId'),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterIndirectionElements(array $ruleMap): array
    {
        $rule = $ruleMap['indirection_el'] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $rule->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;
                $second = $alt->symbols[1] ?? null;

                if (!$first instanceof Terminal) {
                    return true;
                }

                if ($first->value === '[') {
                    return false;
                }

                return !($first->value === '.' && $second instanceof Terminal && $second->value === '*');
            },
        ));

        if ($filtered !== []) {
            $ruleMap['indirection_el'] = new ProductionRule('indirection_el', $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCreatePolicyRule(array $ruleMap): array
    {
        if (!isset($ruleMap['RowSecurityDefaultPermissive'])) {
            return $ruleMap;
        }

        $ruleMap['RowSecurityDefaultPermissive'] = new ProductionRule('RowSecurityDefaultPermissive', [
            new Production([]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCreatePartitionOfRule(array $ruleMap): array
    {
        if (!isset($ruleMap['CreateStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_partition_of_opt_temp'] = new ProductionRule('safe_partition_of_opt_temp', [
            new Production([]),
        ]);
        $ruleMap['CreatePartitionOfStmt'] = new ProductionRule('CreatePartitionOfStmt', [
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_partition_of_opt_temp'),
                new Terminal('TABLE'),
                new NonTerminal('qualified_name'),
                new Terminal('PARTITION'),
                new Terminal('OF'),
                new NonTerminal('qualified_name'),
                new NonTerminal('OptTypedTableElementList'),
                new NonTerminal('PartitionBoundSpec'),
                new NonTerminal('OptPartitionSpec'),
                new NonTerminal('table_access_method_clause'),
                new NonTerminal('OptWith'),
                new NonTerminal('OnCommitOption'),
                new NonTerminal('OptTableSpace'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_partition_of_opt_temp'),
                new Terminal('TABLE'),
                new Terminal('IF_P'),
                new Terminal('NOT'),
                new Terminal('EXISTS'),
                new NonTerminal('qualified_name'),
                new Terminal('PARTITION'),
                new Terminal('OF'),
                new NonTerminal('qualified_name'),
                new NonTerminal('OptTypedTableElementList'),
                new NonTerminal('PartitionBoundSpec'),
                new NonTerminal('OptPartitionSpec'),
                new NonTerminal('table_access_method_clause'),
                new NonTerminal('OptWith'),
                new NonTerminal('OnCommitOption'),
                new NonTerminal('OptTableSpace'),
            ]),
        ]);
        $ruleMap['CreateStmt'] = new ProductionRule('CreateStmt', array_map(
            static function (Production $alt): Production {
                $names = array_map(self::symbolValue(...), $alt->symbols);

                if ($names === ['CREATE', 'OptTemp', 'TABLE', 'qualified_name', 'PARTITION', 'OF', 'qualified_name', 'OptTypedTableElementList', 'PartitionBoundSpec', 'OptPartitionSpec', 'table_access_method_clause', 'OptWith', 'OnCommitOption', 'OptTableSpace']
                    || $names === ['CREATE', 'OptTemp', 'TABLE', 'IF_P', 'NOT', 'EXISTS', 'qualified_name', 'PARTITION', 'OF', 'qualified_name', 'OptTypedTableElementList', 'PartitionBoundSpec', 'OptPartitionSpec', 'table_access_method_clause', 'OptWith', 'OnCommitOption', 'OptTableSpace']) {
                    return new Production([new NonTerminal('CreatePartitionOfStmt')]);
                }

                return $alt;
            },
            $ruleMap['CreateStmt']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterDatabaseRule(array $ruleMap): array
    {
        if (isset($ruleMap['createdb_opt_item'])) {
            $ruleMap['database_bool_option_value'] = new ProductionRule('database_bool_option_value', [
                new Production([new Terminal('TRUE_P')]),
                new Production([new Terminal('FALSE_P')]),
            ]);
            $ruleMap['createdb_opt_item'] = new ProductionRule('createdb_opt_item', [
                new Production([new Terminal('CONNECTION'), new Terminal('LIMIT'), new NonTerminal('opt_equal'), new NonTerminal('SignedIconst')]),
                new Production([new Terminal('CONNECTION'), new Terminal('LIMIT'), new NonTerminal('opt_equal'), new Terminal('DEFAULT')]),
                new Production([new Terminal('ALLOW_CONNECTIONS'), new NonTerminal('opt_equal'), new NonTerminal('database_bool_option_value')]),
                new Production([new Terminal('IS_TEMPLATE'), new NonTerminal('opt_equal'), new NonTerminal('database_bool_option_value')]),
            ]);
        }

        if (isset($ruleMap['createdb_opt_items'])) {
            $ruleMap['createdb_opt_items'] = new ProductionRule('createdb_opt_items', [
                new Production([new NonTerminal('createdb_opt_item')]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentTemporaryRelationCreationRules(array $ruleMap): array
    {
        $ruleMap = $this->ensureTemporaryRelationRules($ruleMap);

        if (isset($ruleMap['CreateStmt'])) {
            $alternatives = [];

            foreach ($ruleMap['CreateStmt']->alternatives as $alt) {
                $names = array_map(self::symbolValue(...), $alt->symbols);

                if ($names === ['CREATE', 'OptTemp', 'TABLE', 'qualified_name', '(', 'OptTableElementList', ')', 'OptInherit', 'OptPartitionSpec', 'table_access_method_clause', 'OptWith', 'OnCommitOption', 'OptTableSpace']) {
                    $alternatives[] = new Production([
                        new Terminal('CREATE'),
                        new NonTerminal('safe_table_non_temp_modifier'),
                        new Terminal('TABLE'),
                        new NonTerminal('qualified_name'),
                        new Terminal('('),
                        new NonTerminal('OptTableElementList'),
                        new Terminal(')'),
                        new NonTerminal('OptInherit'),
                        new NonTerminal('OptPartitionSpec'),
                        new NonTerminal('table_access_method_clause'),
                        new NonTerminal('OptWith'),
                        new NonTerminal('OnCommitOption'),
                        new NonTerminal('OptTableSpace'),
                    ]);
                    $alternatives[] = new Production([
                        new Terminal('CREATE'),
                        new NonTerminal('safe_temporary_relation_modifier'),
                        new Terminal('TABLE'),
                        new NonTerminal('safe_temporary_relation_name'),
                        new Terminal('('),
                        new NonTerminal('OptTableElementList'),
                        new Terminal(')'),
                        new NonTerminal('OptInherit'),
                        new NonTerminal('OptPartitionSpec'),
                        new NonTerminal('table_access_method_clause'),
                        new NonTerminal('OptWith'),
                        new NonTerminal('OnCommitOption'),
                        new NonTerminal('OptTableSpace'),
                    ]);

                    continue;
                }

                if ($names === ['CREATE', 'OptTemp', 'TABLE', 'IF_P', 'NOT', 'EXISTS', 'qualified_name', '(', 'OptTableElementList', ')', 'OptInherit', 'OptPartitionSpec', 'table_access_method_clause', 'OptWith', 'OnCommitOption', 'OptTableSpace']) {
                    $alternatives[] = new Production([
                        new Terminal('CREATE'),
                        new NonTerminal('safe_table_non_temp_modifier'),
                        new Terminal('TABLE'),
                        new Terminal('IF_P'),
                        new Terminal('NOT'),
                        new Terminal('EXISTS'),
                        new NonTerminal('qualified_name'),
                        new Terminal('('),
                        new NonTerminal('OptTableElementList'),
                        new Terminal(')'),
                        new NonTerminal('OptInherit'),
                        new NonTerminal('OptPartitionSpec'),
                        new NonTerminal('table_access_method_clause'),
                        new NonTerminal('OptWith'),
                        new NonTerminal('OnCommitOption'),
                        new NonTerminal('OptTableSpace'),
                    ]);
                    $alternatives[] = new Production([
                        new Terminal('CREATE'),
                        new NonTerminal('safe_temporary_relation_modifier'),
                        new Terminal('TABLE'),
                        new Terminal('IF_P'),
                        new Terminal('NOT'),
                        new Terminal('EXISTS'),
                        new NonTerminal('safe_temporary_relation_name'),
                        new Terminal('('),
                        new NonTerminal('OptTableElementList'),
                        new Terminal(')'),
                        new NonTerminal('OptInherit'),
                        new NonTerminal('OptPartitionSpec'),
                        new NonTerminal('table_access_method_clause'),
                        new NonTerminal('OptWith'),
                        new NonTerminal('OnCommitOption'),
                        new NonTerminal('OptTableSpace'),
                    ]);

                    continue;
                }

                if ($names === ['CREATE', 'OptTemp', 'TABLE', 'qualified_name', 'OF', 'any_name', 'OptTypedTableElementList', 'OptPartitionSpec', 'table_access_method_clause', 'OptWith', 'OnCommitOption', 'OptTableSpace']) {
                    $alternatives[] = new Production([
                        new Terminal('CREATE'),
                        new NonTerminal('safe_table_non_temp_modifier'),
                        new Terminal('TABLE'),
                        new NonTerminal('qualified_name'),
                        new Terminal('OF'),
                        new NonTerminal('any_name'),
                        new NonTerminal('OptTypedTableElementList'),
                        new NonTerminal('OptPartitionSpec'),
                        new NonTerminal('table_access_method_clause'),
                        new NonTerminal('OptWith'),
                        new NonTerminal('OnCommitOption'),
                        new NonTerminal('OptTableSpace'),
                    ]);
                    $alternatives[] = new Production([
                        new Terminal('CREATE'),
                        new NonTerminal('safe_temporary_relation_modifier'),
                        new Terminal('TABLE'),
                        new NonTerminal('safe_temporary_relation_name'),
                        new Terminal('OF'),
                        new NonTerminal('any_name'),
                        new NonTerminal('OptTypedTableElementList'),
                        new NonTerminal('OptPartitionSpec'),
                        new NonTerminal('table_access_method_clause'),
                        new NonTerminal('OptWith'),
                        new NonTerminal('OnCommitOption'),
                        new NonTerminal('OptTableSpace'),
                    ]);

                    continue;
                }

                if ($names === ['CREATE', 'OptTemp', 'TABLE', 'IF_P', 'NOT', 'EXISTS', 'qualified_name', 'OF', 'any_name', 'OptTypedTableElementList', 'OptPartitionSpec', 'table_access_method_clause', 'OptWith', 'OnCommitOption', 'OptTableSpace']) {
                    $alternatives[] = new Production([
                        new Terminal('CREATE'),
                        new NonTerminal('safe_table_non_temp_modifier'),
                        new Terminal('TABLE'),
                        new Terminal('IF_P'),
                        new Terminal('NOT'),
                        new Terminal('EXISTS'),
                        new NonTerminal('qualified_name'),
                        new Terminal('OF'),
                        new NonTerminal('any_name'),
                        new NonTerminal('OptTypedTableElementList'),
                        new NonTerminal('OptPartitionSpec'),
                        new NonTerminal('table_access_method_clause'),
                        new NonTerminal('OptWith'),
                        new NonTerminal('OnCommitOption'),
                        new NonTerminal('OptTableSpace'),
                    ]);
                    $alternatives[] = new Production([
                        new Terminal('CREATE'),
                        new NonTerminal('safe_temporary_relation_modifier'),
                        new Terminal('TABLE'),
                        new Terminal('IF_P'),
                        new Terminal('NOT'),
                        new Terminal('EXISTS'),
                        new NonTerminal('safe_temporary_relation_name'),
                        new Terminal('OF'),
                        new NonTerminal('any_name'),
                        new NonTerminal('OptTypedTableElementList'),
                        new NonTerminal('OptPartitionSpec'),
                        new NonTerminal('table_access_method_clause'),
                        new NonTerminal('OptWith'),
                        new NonTerminal('OnCommitOption'),
                        new NonTerminal('OptTableSpace'),
                    ]);

                    continue;
                }

                $alternatives[] = $alt;
            }

            $ruleMap['CreateStmt'] = new ProductionRule('CreateStmt', $alternatives);
        }

        if (isset($ruleMap['CreateSeqStmt'])) {
            $ruleMap['CreateSeqStmt'] = new ProductionRule('CreateSeqStmt', [
                new Production([
                    new Terminal('CREATE'),
                    new NonTerminal('safe_sequence_non_temp_modifier'),
                    new Terminal('SEQUENCE'),
                    new NonTerminal('qualified_name'),
                    new NonTerminal('OptSeqOptList'),
                ]),
                new Production([
                    new Terminal('CREATE'),
                    new NonTerminal('safe_temporary_relation_modifier'),
                    new Terminal('SEQUENCE'),
                    new NonTerminal('safe_temporary_relation_name'),
                    new NonTerminal('OptSeqOptList'),
                ]),
                new Production([
                    new Terminal('CREATE'),
                    new NonTerminal('safe_sequence_non_temp_modifier'),
                    new Terminal('SEQUENCE'),
                    new Terminal('IF_P'),
                    new Terminal('NOT'),
                    new Terminal('EXISTS'),
                    new NonTerminal('qualified_name'),
                    new NonTerminal('OptSeqOptList'),
                ]),
                new Production([
                    new Terminal('CREATE'),
                    new NonTerminal('safe_temporary_relation_modifier'),
                    new Terminal('SEQUENCE'),
                    new Terminal('IF_P'),
                    new Terminal('NOT'),
                    new Terminal('EXISTS'),
                    new NonTerminal('safe_temporary_relation_name'),
                    new NonTerminal('OptSeqOptList'),
                ]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterRoleRule(array $ruleMap): array
    {
        $ruleMap['valid_until_literal'] = new ProductionRule('valid_until_literal', [
            new Production([new Terminal("'2000-01-01 00:00:00+00'")]),
            new Production([new Terminal("'2030-12-31 23:59:59+00'")]),
        ]);

        if (isset($ruleMap['AlterOptRoleElem'])) {
            $ruleMap['AlterOptRoleElem'] = new ProductionRule('AlterOptRoleElem', array_map(
                static function (Production $alt): Production {
                    $symbols = $alt->symbols;
                    if (count($symbols) === 3
                        && $symbols[0] instanceof Terminal
                        && $symbols[0]->value === 'VALID'
                        && $symbols[1] instanceof Terminal
                        && $symbols[1]->value === 'UNTIL'
                        && $symbols[2] instanceof NonTerminal
                        && $symbols[2]->value === 'Sconst') {
                        $symbols[2] = new NonTerminal('valid_until_literal');
                    }

                    return new Production($symbols);
                },
                array_values(array_filter(
                    $ruleMap['AlterOptRoleElem']->alternatives,
                    static function (Production $alt): bool {
                        $first = $alt->symbols[0] ?? null;

                        return !$first instanceof Terminal || $first->value !== 'IDENT';
                    },
                )),
            ));
        }

        if (isset($ruleMap['AlterOptRoleList'])) {
            $ruleMap['AlterOptRoleList'] = new ProductionRule('AlterOptRoleList', [
                new Production([]),
                new Production([new NonTerminal('AlterOptRoleElem')]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterRoutineRule(array $ruleMap): array
    {
        if (!isset($ruleMap['AlterFunctionStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_alter_routine_parallel_value'] = new ProductionRule('safe_alter_routine_parallel_value', [
            new Production([new Terminal('SAFE')]),
            new Production([new Terminal('RESTRICTED')]),
            new Production([new Terminal('UNSAFE')]),
        ]);
        $ruleMap['safe_alter_routine_option'] = new ProductionRule('safe_alter_routine_option', [
            new Production([new Terminal('CALLED'), new Terminal('ON'), new Terminal('NULL_P'), new Terminal('INPUT_P')]),
            new Production([new Terminal('RETURNS'), new Terminal('NULL_P'), new Terminal('ON'), new Terminal('NULL_P'), new Terminal('INPUT_P')]),
            new Production([new Terminal('STRICT_P')]),
            new Production([new Terminal('IMMUTABLE')]),
            new Production([new Terminal('STABLE')]),
            new Production([new Terminal('VOLATILE')]),
            new Production([new Terminal('EXTERNAL'), new Terminal('SECURITY'), new Terminal('DEFINER')]),
            new Production([new Terminal('EXTERNAL'), new Terminal('SECURITY'), new Terminal('INVOKER')]),
            new Production([new Terminal('SECURITY'), new Terminal('DEFINER')]),
            new Production([new Terminal('SECURITY'), new Terminal('INVOKER')]),
            new Production([new Terminal('LEAKPROOF')]),
            new Production([new Terminal('NOT'), new Terminal('LEAKPROOF')]),
            new Production([new Terminal('COST'), new NonTerminal('NumericOnly')]),
            new Production([new Terminal('ROWS'), new NonTerminal('NumericOnly')]),
            new Production([new Terminal('SUPPORT'), new NonTerminal('any_name')]),
            new Production([new NonTerminal('FunctionSetResetClause')]),
            new Production([new Terminal('PARALLEL'), new NonTerminal('safe_alter_routine_parallel_value')]),
        ]);
        $ruleMap['safe_alter_routine_option_list'] = new ProductionRule('safe_alter_routine_option_list', [
            new Production([new NonTerminal('safe_alter_routine_option')]),
        ]);
        $ruleMap['AlterFunctionStmt'] = new ProductionRule('AlterFunctionStmt', [
            new Production([new Terminal('ALTER'), new Terminal('FUNCTION'), new NonTerminal('function_with_argtypes'), new NonTerminal('safe_alter_routine_option_list'), new NonTerminal('opt_restrict')]),
            new Production([new Terminal('ALTER'), new Terminal('PROCEDURE'), new NonTerminal('function_with_argtypes'), new NonTerminal('safe_alter_routine_option_list'), new NonTerminal('opt_restrict')]),
            new Production([new Terminal('ALTER'), new Terminal('ROUTINE'), new NonTerminal('function_with_argtypes'), new NonTerminal('safe_alter_routine_option_list'), new NonTerminal('opt_restrict')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCreateRoleRule(array $ruleMap): array
    {
        if (!isset($ruleMap['CreateRoleStmt']) && !isset($ruleMap['CreateUserStmt']) && !isset($ruleMap['CreateGroupStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_role_name'] = new ProductionRule('safe_role_name', [
            new Production([new NonTerminal('safe_create_role_name')]),
        ]);
        $ruleMap['safe_role_name_list'] = new ProductionRule('safe_role_name_list', [
            new Production([new NonTerminal('safe_role_name')]),
            new Production([
                new NonTerminal('safe_role_name_list'),
                new Terminal(','),
                new NonTerminal('safe_role_name'),
            ]),
        ]);
        $ruleMap['safe_create_role_name'] = new ProductionRule('safe_create_role_name', [
            new Production([new NonTerminal('NonReservedWord')]),
        ]);
        $ruleMap['safe_create_role_name_list'] = new ProductionRule('safe_create_role_name_list', [
            new Production([new NonTerminal('safe_create_role_name')]),
            new Production([
                new NonTerminal('safe_create_role_name_list'),
                new Terminal(','),
                new NonTerminal('safe_create_role_name'),
            ]),
        ]);
        $ruleMap['safe_create_role_connection_limit'] = new ProductionRule('safe_create_role_connection_limit', [
            new Production([new Terminal('-1')]),
            new Production([new Terminal('0')]),
            new Production([new Terminal('1')]),
            new Production([new Terminal('10')]),
        ]);
        $ruleMap['safe_create_role_option'] = new ProductionRule('safe_create_role_option', [
            new Production([new Terminal('INHERIT')]),
            new Production([new Terminal('CONNECTION'), new Terminal('LIMIT'), new NonTerminal('safe_create_role_connection_limit')]),
            new Production([new Terminal('VALID'), new Terminal('UNTIL'), new NonTerminal('valid_until_literal')]),
            new Production([new Terminal('ADMIN'), new NonTerminal('safe_create_role_name_list')]),
            new Production([new Terminal('ROLE'), new NonTerminal('safe_create_role_name_list')]),
            new Production([new Terminal('IN_P'), new Terminal('ROLE'), new NonTerminal('safe_create_role_name_list')]),
            new Production([new Terminal('IN_P'), new Terminal('GROUP_P'), new NonTerminal('safe_create_role_name_list')]),
        ]);
        $ruleMap['safe_create_role_option_list'] = new ProductionRule('safe_create_role_option_list', [
            new Production([]),
            new Production([new NonTerminal('safe_create_role_option')]),
        ]);
        if (isset($ruleMap['CreateRoleStmt'])) {
            $ruleMap['CreateRoleStmt'] = new ProductionRule('CreateRoleStmt', [
                new Production([
                    new Terminal('CREATE'),
                    new Terminal('ROLE'),
                    new NonTerminal('safe_create_role_name'),
                    new NonTerminal('opt_with'),
                    new NonTerminal('safe_create_role_option_list'),
                ]),
            ]);
        }

        if (isset($ruleMap['CreateUserStmt'])) {
            $ruleMap['CreateUserStmt'] = new ProductionRule('CreateUserStmt', [
                new Production([
                    new Terminal('CREATE'),
                    new Terminal('USER'),
                    new NonTerminal('safe_create_role_name'),
                    new NonTerminal('opt_with'),
                    new NonTerminal('safe_create_role_option_list'),
                ]),
            ]);
        }

        if (isset($ruleMap['CreateGroupStmt'])) {
            $ruleMap['CreateGroupStmt'] = new ProductionRule('CreateGroupStmt', [
                new Production([
                    new Terminal('CREATE'),
                    new Terminal('GROUP_P'),
                    new NonTerminal('safe_create_role_name'),
                    new NonTerminal('opt_with'),
                    new NonTerminal('safe_create_role_option_list'),
                ]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentDropRoleRule(array $ruleMap): array
    {
        if (!isset($ruleMap['DropRoleStmt'])) {
            return $ruleMap;
        }

        if (!isset($ruleMap['safe_role_name'], $ruleMap['safe_role_name_list'])) {
            $ruleMap['safe_create_role_name'] = $ruleMap['safe_create_role_name'] ?? new ProductionRule('safe_create_role_name', [
                new Production([new NonTerminal('NonReservedWord')]),
            ]);
            $ruleMap['safe_role_name'] = new ProductionRule('safe_role_name', [
                new Production([new NonTerminal('safe_create_role_name')]),
            ]);
            $ruleMap['safe_role_name_list'] = new ProductionRule('safe_role_name_list', [
                new Production([new NonTerminal('safe_role_name')]),
                new Production([
                    new NonTerminal('safe_role_name_list'),
                    new Terminal(','),
                    new NonTerminal('safe_role_name'),
                ]),
            ]);
        }

        $ruleMap['DropRoleStmt'] = new ProductionRule('DropRoleStmt', [
            new Production([new Terminal('DROP'), new Terminal('ROLE'), new NonTerminal('safe_role_name_list')]),
            new Production([new Terminal('DROP'), new Terminal('ROLE'), new Terminal('IF_P'), new Terminal('EXISTS'), new NonTerminal('safe_role_name_list')]),
            new Production([new Terminal('DROP'), new Terminal('USER'), new NonTerminal('safe_role_name_list')]),
            new Production([new Terminal('DROP'), new Terminal('USER'), new Terminal('IF_P'), new Terminal('EXISTS'), new NonTerminal('safe_role_name_list')]),
            new Production([new Terminal('DROP'), new Terminal('GROUP_P'), new NonTerminal('safe_role_name_list')]),
            new Production([new Terminal('DROP'), new Terminal('GROUP_P'), new Terminal('IF_P'), new Terminal('EXISTS'), new NonTerminal('safe_role_name_list')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterTableRule(array $ruleMap): array
    {
        if (!isset($ruleMap['AlterTableStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_materialized_view_column_position'] = new ProductionRule('safe_materialized_view_column_position', [
            new Production([new Terminal('1')]),
            new Production([new Terminal('2')]),
            new Production([new Terminal('16')]),
        ]);
        $ruleMap['safe_materialized_view_statistics_value'] = new ProductionRule('safe_materialized_view_statistics_value', [
            new Production([new Terminal('1')]),
            new Production([new Terminal('100')]),
            new Production([new Terminal('1000')]),
        ]);
        $ruleMap['materialized_view_alter_table_cmd'] = new ProductionRule('materialized_view_alter_table_cmd', [
            new Production([new Terminal('ALTER'), new NonTerminal('opt_column'), new NonTerminal('ColId'), new Terminal('SET'), new Terminal('STATISTICS'), new NonTerminal('safe_materialized_view_statistics_value')]),
            new Production([new Terminal('ALTER'), new NonTerminal('opt_column'), new NonTerminal('safe_materialized_view_column_position'), new Terminal('SET'), new Terminal('STATISTICS'), new NonTerminal('safe_materialized_view_statistics_value')]),
            new Production([new Terminal('CLUSTER'), new Terminal('ON'), new NonTerminal('name')]),
            new Production([new Terminal('SET'), new Terminal('WITHOUT'), new Terminal('CLUSTER')]),
            new Production([new Terminal('OWNER'), new Terminal('TO'), new NonTerminal('RoleSpec')]),
            new Production([new Terminal('SET'), new Terminal('TABLESPACE'), new NonTerminal('name')]),
            new Production([new Terminal('SET'), new NonTerminal('reloptions')]),
            new Production([new Terminal('RESET'), new NonTerminal('reloptions')]),
        ]);
        $ruleMap['materialized_view_alter_table_cmds'] = new ProductionRule('materialized_view_alter_table_cmds', [
            new Production([new NonTerminal('materialized_view_alter_table_cmd')]),
        ]);
        $ruleMap['AlterTableStmt'] = new ProductionRule('AlterTableStmt', array_map(
            static function (Production $alt): Production {
                $symbols = $alt->symbols;
                if (count($symbols) === 7
                    && $symbols[0] instanceof Terminal
                    && $symbols[0]->value === 'ALTER'
                    && $symbols[1] instanceof Terminal
                    && $symbols[1]->value === 'MATERIALIZED'
                    && $symbols[2] instanceof Terminal
                    && $symbols[2]->value === 'VIEW'
                    && $symbols[3] instanceof Terminal
                    && $symbols[3]->value === 'IF_P'
                    && $symbols[4] instanceof Terminal
                    && $symbols[4]->value === 'EXISTS'
                    && $symbols[5] instanceof NonTerminal
                    && $symbols[5]->value === 'qualified_name'
                    && $symbols[6] instanceof NonTerminal
                    && $symbols[6]->value === 'alter_table_cmds') {
                    $symbols[6] = new NonTerminal('materialized_view_alter_table_cmds');

                    return new Production($symbols);
                }

                if (count($symbols) === 5
                    && $symbols[0] instanceof Terminal
                    && $symbols[0]->value === 'ALTER'
                    && $symbols[1] instanceof Terminal
                    && $symbols[1]->value === 'MATERIALIZED'
                    && $symbols[2] instanceof Terminal
                    && $symbols[2]->value === 'VIEW'
                    && $symbols[3] instanceof NonTerminal
                    && $symbols[3]->value === 'qualified_name'
                    && $symbols[4] instanceof NonTerminal
                    && $symbols[4]->value === 'alter_table_cmds') {
                    $symbols[4] = new NonTerminal('materialized_view_alter_table_cmds');

                    return new Production($symbols);
                }

                return $alt;
            },
            $ruleMap['AlterTableStmt']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterIndexRule(array $ruleMap): array
    {
        if (!isset($ruleMap['AlterTableStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_index_column_position'] = new ProductionRule('safe_index_column_position', [
            new Production([new Terminal('1')]),
            new Production([new Terminal('2')]),
            new Production([new Terminal('16')]),
        ]);
        $ruleMap['safe_index_statistics_value'] = new ProductionRule('safe_index_statistics_value', [
            new Production([new Terminal('1')]),
            new Production([new Terminal('100')]),
            new Production([new Terminal('1000')]),
        ]);
        $ruleMap['index_alter_table_cmd'] = new ProductionRule('index_alter_table_cmd', [
            new Production([new Terminal('ALTER'), new NonTerminal('opt_column'), new NonTerminal('ColId'), new Terminal('SET'), new Terminal('STATISTICS'), new NonTerminal('safe_index_statistics_value')]),
            new Production([new Terminal('ALTER'), new NonTerminal('opt_column'), new NonTerminal('safe_index_column_position'), new Terminal('SET'), new Terminal('STATISTICS'), new NonTerminal('safe_index_statistics_value')]),
            new Production([new Terminal('OWNER'), new Terminal('TO'), new NonTerminal('RoleSpec')]),
            new Production([new Terminal('SET'), new Terminal('TABLESPACE'), new NonTerminal('name')]),
            new Production([new Terminal('SET'), new NonTerminal('reloptions')]),
            new Production([new Terminal('RESET'), new NonTerminal('reloptions')]),
        ]);
        $ruleMap['index_alter_table_cmds'] = new ProductionRule('index_alter_table_cmds', [
            new Production([new NonTerminal('index_alter_table_cmd')]),
        ]);
        $ruleMap['AlterIndexStmt'] = new ProductionRule('AlterIndexStmt', [
            new Production([
                new Terminal('ALTER'),
                new Terminal('INDEX'),
                new NonTerminal('qualified_name'),
                new NonTerminal('index_alter_table_cmds'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('INDEX'),
                new Terminal('IF_P'),
                new Terminal('EXISTS'),
                new NonTerminal('qualified_name'),
                new NonTerminal('index_alter_table_cmds'),
            ]),
        ]);
        $ruleMap['AlterTableStmt'] = new ProductionRule('AlterTableStmt', array_map(
            static function (Production $alt): Production {
                $symbols = $alt->symbols;
                $names = array_map(self::symbolValue(...), $symbols);

                if ($names === ['ALTER', 'INDEX', 'qualified_name', 'alter_table_cmds']) {
                    return new Production([new NonTerminal('AlterIndexStmt')]);
                }

                if ($names === ['ALTER', 'INDEX', 'IF_P', 'EXISTS', 'qualified_name', 'alter_table_cmds']) {
                    return new Production([new NonTerminal('AlterIndexStmt')]);
                }

                return $alt;
            },
            $ruleMap['AlterTableStmt']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterDomainRule(array $ruleMap): array
    {
        if (!isset($ruleMap['AlterDomainStmt'])) {
            return $ruleMap;
        }

        $ruleMap['domain_constraint_attribute_spec'] = new ProductionRule('domain_constraint_attribute_spec', [
            new Production([]),
            new Production([new Terminal('NOT'), new Terminal('DEFERRABLE')]),
            new Production([new Terminal('INITIALLY'), new Terminal('IMMEDIATE')]),
            new Production([new Terminal('NOT'), new Terminal('VALID')]),
            new Production([new Terminal('NO'), new Terminal('INHERIT')]),
        ]);
        $ruleMap['domain_add_constraint'] = new ProductionRule('domain_add_constraint', [
            new Production([
                new Terminal('CHECK'),
                new Terminal('('),
                new NonTerminal('a_expr'),
                new Terminal(')'),
                new NonTerminal('domain_constraint_attribute_spec'),
            ]),
            new Production([
                new Terminal('CONSTRAINT'),
                new NonTerminal('name'),
                new Terminal('CHECK'),
                new Terminal('('),
                new NonTerminal('a_expr'),
                new Terminal(')'),
                new NonTerminal('domain_constraint_attribute_spec'),
            ]),
        ]);
        $ruleMap['AlterDomainStmt'] = new ProductionRule('AlterDomainStmt', [
            new Production([new Terminal('ALTER'), new Terminal('DOMAIN_P'), new NonTerminal('any_name'), new NonTerminal('alter_column_default')]),
            new Production([new Terminal('ALTER'), new Terminal('DOMAIN_P'), new NonTerminal('any_name'), new Terminal('DROP'), new Terminal('NOT'), new Terminal('NULL_P')]),
            new Production([new Terminal('ALTER'), new Terminal('DOMAIN_P'), new NonTerminal('any_name'), new Terminal('SET'), new Terminal('NOT'), new Terminal('NULL_P')]),
            new Production([new Terminal('ALTER'), new Terminal('DOMAIN_P'), new NonTerminal('any_name'), new Terminal('ADD_P'), new NonTerminal('domain_add_constraint')]),
            new Production([new Terminal('ALTER'), new Terminal('DOMAIN_P'), new NonTerminal('any_name'), new Terminal('DROP'), new Terminal('CONSTRAINT'), new NonTerminal('name'), new NonTerminal('opt_drop_behavior')]),
            new Production([new Terminal('ALTER'), new Terminal('DOMAIN_P'), new NonTerminal('any_name'), new Terminal('DROP'), new Terminal('CONSTRAINT'), new Terminal('IF_P'), new Terminal('EXISTS'), new NonTerminal('name'), new NonTerminal('opt_drop_behavior')]),
            new Production([new Terminal('ALTER'), new Terminal('DOMAIN_P'), new NonTerminal('any_name'), new Terminal('VALIDATE'), new Terminal('CONSTRAINT'), new NonTerminal('name')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterViewRule(array $ruleMap): array
    {
        if (!isset($ruleMap['AlterTableStmt'])) {
            return $ruleMap;
        }

        $ruleMap['view_alter_table_cmd'] = new ProductionRule('view_alter_table_cmd', [
            new Production([new Terminal('ALTER'), new NonTerminal('opt_column'), new NonTerminal('ColId'), new Terminal('SET'), new Terminal('DEFAULT'), new NonTerminal('a_expr')]),
            new Production([new Terminal('ALTER'), new NonTerminal('opt_column'), new NonTerminal('ColId'), new Terminal('DROP'), new Terminal('DEFAULT')]),
            new Production([new Terminal('OWNER'), new Terminal('TO'), new NonTerminal('RoleSpec')]),
            new Production([new Terminal('SET'), new NonTerminal('reloptions')]),
            new Production([new Terminal('RESET'), new NonTerminal('reloptions')]),
        ]);
        $ruleMap['view_alter_table_cmds'] = new ProductionRule('view_alter_table_cmds', [
            new Production([new NonTerminal('view_alter_table_cmd')]),
        ]);
        $ruleMap['AlterTableStmt'] = new ProductionRule('AlterTableStmt', array_map(
            static function (Production $alt): Production {
                $symbols = $alt->symbols;
                $names = array_map(self::symbolValue(...), $symbols);

                if ($names === ['ALTER', 'VIEW', 'qualified_name', 'alter_table_cmds']) {
                    $symbols[3] = new NonTerminal('view_alter_table_cmds');

                    return new Production(array_values($symbols));
                }

                if ($names === ['ALTER', 'VIEW', 'IF_P', 'EXISTS', 'qualified_name', 'alter_table_cmds']) {
                    $symbols[5] = new NonTerminal('view_alter_table_cmds');

                    return new Production(array_values($symbols));
                }

                return $alt;
            },
            $ruleMap['AlterTableStmt']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterTypeRule(array $ruleMap): array
    {
        if (!isset($ruleMap['AlterTypeStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_alter_type_option'] = new ProductionRule('safe_alter_type_option', [
            new Production([new Terminal('RECEIVE'), new Terminal('='), new Terminal('NONE')]),
            new Production([new Terminal('SEND'), new Terminal('='), new Terminal('NONE')]),
            new Production([new Terminal('TYPMOD_IN'), new Terminal('='), new Terminal('NONE')]),
            new Production([new Terminal('TYPMOD_OUT'), new Terminal('='), new Terminal('NONE')]),
            new Production([new Terminal('ANALYZE'), new Terminal('='), new Terminal('NONE')]),
        ]);
        $ruleMap['safe_alter_type_option_list'] = new ProductionRule('safe_alter_type_option_list', [
            new Production([new NonTerminal('safe_alter_type_option')]),
            new Production([
                new NonTerminal('safe_alter_type_option_list'),
                new Terminal(','),
                new NonTerminal('safe_alter_type_option'),
            ]),
        ]);
        $ruleMap['AlterTypeStmt'] = new ProductionRule('AlterTypeStmt', [
            new Production([
                new Terminal('ALTER'),
                new Terminal('TYPE_P'),
                new NonTerminal('any_name'),
                new Terminal('SET'),
                new Terminal('('),
                new NonTerminal('safe_alter_type_option_list'),
                new Terminal(')'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterSequenceRule(array $ruleMap): array
    {
        if (!isset($ruleMap['AlterTableStmt'])) {
            return $ruleMap;
        }

        $ruleMap['sequence_alter_table_cmd'] = new ProductionRule('sequence_alter_table_cmd', [
            new Production([new Terminal('OWNER'), new Terminal('TO'), new NonTerminal('RoleSpec')]),
        ]);
        $ruleMap['sequence_alter_table_cmds'] = new ProductionRule('sequence_alter_table_cmds', [
            new Production([new NonTerminal('sequence_alter_table_cmd')]),
        ]);
        $ruleMap['AlterTableStmt'] = new ProductionRule('AlterTableStmt', array_map(
            static function (Production $alt): Production {
                $symbols = $alt->symbols;
                $names = array_map(self::symbolValue(...), $symbols);

                if ($names === ['ALTER', 'SEQUENCE', 'qualified_name', 'alter_table_cmds']) {
                    return new Production([
                        new Terminal('ALTER'),
                        new Terminal('SEQUENCE'),
                        new NonTerminal('qualified_name'),
                        new NonTerminal('sequence_alter_table_cmds'),
                    ]);
                }

                if ($names === ['ALTER', 'SEQUENCE', 'IF_P', 'EXISTS', 'qualified_name', 'alter_table_cmds']) {
                    return new Production([
                        new Terminal('ALTER'),
                        new Terminal('SEQUENCE'),
                        new Terminal('IF_P'),
                        new Terminal('EXISTS'),
                        new NonTerminal('qualified_name'),
                        new NonTerminal('sequence_alter_table_cmds'),
                    ]);
                }

                return $alt;
            },
            $ruleMap['AlterTableStmt']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterStatisticsRule(array $ruleMap): array
    {
        if (!isset($ruleMap['AlterStatsStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_alter_statistics_value'] = new ProductionRule('safe_alter_statistics_value', [
            new Production([new Terminal('1')]),
            new Production([new Terminal('100')]),
            new Production([new Terminal('1000')]),
        ]);
        $ruleMap['AlterStatsStmt'] = new ProductionRule('AlterStatsStmt', [
            new Production([
                new Terminal('ALTER'),
                new Terminal('STATISTICS'),
                new NonTerminal('any_name'),
                new Terminal('SET'),
                new Terminal('STATISTICS'),
                new NonTerminal('safe_alter_statistics_value'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('STATISTICS'),
                new Terminal('IF_P'),
                new Terminal('EXISTS'),
                new NonTerminal('any_name'),
                new Terminal('SET'),
                new Terminal('STATISTICS'),
                new NonTerminal('safe_alter_statistics_value'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAccessMethodRule(array $ruleMap): array
    {
        if (!isset($ruleMap['set_access_method_name'])) {
            return $ruleMap;
        }

        $ruleMap['set_access_method_name'] = new ProductionRule('set_access_method_name', [
            new Production([new NonTerminal('ColId')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterEnumRule(array $ruleMap): array
    {
        if (!isset($ruleMap['AlterEnumStmt'])) {
            return $ruleMap;
        }

        $ruleMap['AlterEnumStmt'] = new ProductionRule('AlterEnumStmt', array_values(array_filter(
            $ruleMap['AlterEnumStmt']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    self::symbolValue(...),
                    $alt->symbols,
                );

                return $names !== ['ALTER', 'TYPE_P', 'any_name', 'DROP', 'VALUE_P', 'Sconst'];
            },
        )));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentFunctionWithArgtypesRule(array $ruleMap): array
    {
        if (!isset($ruleMap['function_with_argtypes'])) {
            return $ruleMap;
        }

        $ruleMap['function_with_argtypes'] = new ProductionRule('function_with_argtypes', [
            new Production([new NonTerminal('func_name'), new NonTerminal('func_args')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentEventTriggerRule(array $ruleMap): array
    {
        if (!isset($ruleMap['CreateEventTrigStmt'])) {
            return $ruleMap;
        }

        $ruleMap['event_trigger_event_name'] = new ProductionRule('event_trigger_event_name', [
            new Production([new Terminal('ddl_command_start')]),
            new Production([new Terminal('ddl_command_end')]),
            new Production([new Terminal('sql_drop')]),
            new Production([new Terminal('table_rewrite')]),
        ]);
        $ruleMap['CreateEventTrigStmt'] = new ProductionRule('CreateEventTrigStmt', [
            new Production([
                new Terminal('CREATE'),
                new Terminal('EVENT'),
                new Terminal('TRIGGER'),
                new NonTerminal('name'),
                new Terminal('ON'),
                new NonTerminal('event_trigger_event_name'),
                new Terminal('EXECUTE'),
                new NonTerminal('FUNCTION_or_PROCEDURE'),
                new NonTerminal('func_name'),
                new Terminal('('),
                new Terminal(')'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAnalyzeVacuumRule(array $ruleMap): array
    {
        if (isset($ruleMap['VacuumStmt'])) {
            $ruleMap['VacuumStmt'] = new ProductionRule('VacuumStmt', [
                new Production([
                    new Terminal('VACUUM'),
                    new NonTerminal('opt_full'),
                    new NonTerminal('opt_freeze'),
                    new NonTerminal('opt_verbose'),
                    new NonTerminal('opt_analyze'),
                    new NonTerminal('opt_vacuum_relation_list'),
                ]),
            ]);
        }

        if (isset($ruleMap['AnalyzeStmt'])) {
            $ruleMap['AnalyzeStmt'] = new ProductionRule('AnalyzeStmt', [
                new Production([
                    new NonTerminal('analyze_keyword'),
                    new NonTerminal('opt_verbose'),
                    new NonTerminal('opt_vacuum_relation_list'),
                ]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentOptionListRules(array $ruleMap): array
    {
        if (isset($ruleMap['DefACLOptionList'])) {
            $ruleMap['DefACLOptionList'] = new ProductionRule('DefACLOptionList', [
                new Production([]),
                new Production([new NonTerminal('DefACLOption')]),
            ]);
        }

        if (isset($ruleMap['create_extension_opt_list'])) {
            $ruleMap['create_extension_opt_list'] = new ProductionRule('create_extension_opt_list', [
                new Production([]),
                new Production([new NonTerminal('create_extension_opt_item')]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentUtilityStatementRules(array $ruleMap): array
    {
        if (isset($ruleMap['ClusterStmt'])) {
            $ruleMap['ClusterStmt'] = new ProductionRule('ClusterStmt', [
                new Production([new Terminal('CLUSTER'), new NonTerminal('opt_verbose'), new NonTerminal('qualified_name'), new NonTerminal('cluster_index_specification')]),
                new Production([new Terminal('CLUSTER'), new NonTerminal('opt_verbose')]),
                new Production([new Terminal('CLUSTER'), new NonTerminal('opt_verbose'), new NonTerminal('name'), new Terminal('ON'), new NonTerminal('qualified_name')]),
            ]);
        }

        if (isset($ruleMap['ExplainStmt'])) {
            $ruleMap['ExplainStmt'] = new ProductionRule('ExplainStmt', [
                new Production([new Terminal('EXPLAIN'), new NonTerminal('ExplainableStmt')]),
                new Production([new Terminal('EXPLAIN'), new NonTerminal('analyze_keyword'), new NonTerminal('opt_verbose'), new NonTerminal('ExplainableStmt')]),
                new Production([new Terminal('EXPLAIN'), new Terminal('VERBOSE'), new NonTerminal('ExplainableStmt')]),
            ]);
        }

        if (isset($ruleMap['opt_reindex_option_list'])) {
            $ruleMap['opt_reindex_option_list'] = new ProductionRule('opt_reindex_option_list', [
                new Production([]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentGrantRoleRule(array $ruleMap): array
    {
        if (!isset($ruleMap['GrantRoleStmt'])) {
            return $ruleMap;
        }

        $ruleMap['GrantRoleStmt'] = new ProductionRule('GrantRoleStmt', [
            new Production([
                new Terminal('GRANT'),
                new NonTerminal('safe_role_name_list'),
                new Terminal('TO'),
                new NonTerminal('safe_role_name_list'),
                new NonTerminal('opt_granted_by'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentParameterTargetRule(array $ruleMap): array
    {
        $privilegeTarget = $ruleMap['privilege_target'] ?? null;
        if ($privilegeTarget === null) {
            return $ruleMap;
        }

        $ruleMap['safe_configuration_parameter_name'] = new ProductionRule('safe_configuration_parameter_name', [
            new Production([new Terminal('search_path')]),
            new Production([new Terminal('work_mem')]),
            new Production([new Terminal('maintenance_work_mem')]),
            new Production([new Terminal('statement_timeout')]),
            new Production([new Terminal('application_name')]),
        ]);
        $ruleMap['safe_configuration_parameter_name_list'] = new ProductionRule('safe_configuration_parameter_name_list', [
            new Production([new NonTerminal('safe_configuration_parameter_name')]),
            new Production([
                new NonTerminal('safe_configuration_parameter_name_list'),
                new Terminal(','),
                new NonTerminal('safe_configuration_parameter_name'),
            ]),
        ]);
        $ruleMap['privilege_target'] = new ProductionRule('privilege_target', array_map(
            static function (Production $alt): Production {
                $symbols = $alt->symbols;
                $names = array_map(self::symbolValue(...), $symbols);

                if ($names === ['PARAMETER', 'parameter_name_list']) {
                    return new Production([
                        new Terminal('PARAMETER'),
                        new NonTerminal('safe_configuration_parameter_name_list'),
                    ]);
                }

                return $alt;
            },
            $privilegeTarget->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAlterExtensionContentsRule(array $ruleMap): array
    {
        if (!isset($ruleMap['AlterExtensionContentsStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_extension_object_type_name'] = new ProductionRule('safe_extension_object_type_name', [
            new Production([new Terminal('ACCESS'), new Terminal('METHOD')]),
            new Production([new Terminal('EVENT'), new Terminal('TRIGGER')]),
            new Production([new Terminal('FOREIGN'), new Terminal('DATA_P'), new Terminal('WRAPPER')]),
            new Production([new NonTerminal('opt_procedural'), new Terminal('LANGUAGE')]),
            new Production([new Terminal('SCHEMA')]),
            new Production([new Terminal('SERVER')]),
        ]);
        $ruleMap['AlterExtensionContentsStmt'] = new ProductionRule('AlterExtensionContentsStmt', [
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new NonTerminal('safe_extension_object_type_name'),
                new NonTerminal('name'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new NonTerminal('object_type_any_name'),
                new NonTerminal('any_name'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('AGGREGATE'),
                new NonTerminal('aggregate_with_argtypes'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('CAST'),
                new NonTerminal('safe_cast_signature'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('DOMAIN_P'),
                new NonTerminal('any_name'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('FUNCTION'),
                new NonTerminal('function_with_argtypes'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('OPERATOR'),
                new NonTerminal('operator_with_argtypes'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('OPERATOR'),
                new Terminal('CLASS'),
                new NonTerminal('any_name'),
                new Terminal('USING'),
                new NonTerminal('name'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('OPERATOR'),
                new Terminal('FAMILY'),
                new NonTerminal('any_name'),
                new Terminal('USING'),
                new NonTerminal('name'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('PROCEDURE'),
                new NonTerminal('function_with_argtypes'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('ROUTINE'),
                new NonTerminal('function_with_argtypes'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('TRANSFORM'),
                new Terminal('FOR'),
                new NonTerminal('safe_type_reference'),
                new Terminal('LANGUAGE'),
                new NonTerminal('name'),
            ]),
            new Production([
                new Terminal('ALTER'),
                new Terminal('EXTENSION'),
                new NonTerminal('name'),
                new NonTerminal('add_drop'),
                new Terminal('TYPE_P'),
                new NonTerminal('any_name'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function ensureSafeTypeReferenceRules(array $ruleMap): array
    {
        $ruleMap['safe_type_reference'] = new ProductionRule('safe_type_reference', [
            new Production([new Terminal('INTEGER')]),
            new Production([new Terminal('TEXT')]),
            new Production([new Terminal('BOOLEAN')]),
            new Production([new Terminal('BYTEA')]),
            new Production([new Terminal('DATE')]),
        ]);
        $ruleMap['safe_cast_signature'] = new ProductionRule('safe_cast_signature', [
            new Production([new Terminal('('), new Terminal('INTEGER'), new Terminal('AS'), new Terminal('TEXT'), new Terminal(')')]),
            new Production([new Terminal('('), new Terminal('TEXT'), new Terminal('AS'), new Terminal('INTEGER'), new Terminal(')')]),
            new Production([new Terminal('('), new Terminal('BOOLEAN'), new Terminal('AS'), new Terminal('TEXT'), new Terminal(')')]),
            new Production([new Terminal('('), new Terminal('DATE'), new Terminal('AS'), new Terminal('TEXT'), new Terminal(')')]),
            new Production([new Terminal('('), new Terminal('BYTEA'), new Terminal('AS'), new Terminal('TEXT'), new Terminal(')')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentLargeObjectTargetRule(array $ruleMap): array
    {
        $privilegeTarget = $ruleMap['privilege_target'] ?? null;
        if ($privilegeTarget === null) {
            return $ruleMap;
        }

        $ruleMap['safe_large_object_oid'] = new ProductionRule('safe_large_object_oid', [
            new Production([new Terminal('1')]),
            new Production([new Terminal('42')]),
            new Production([new Terminal('1024')]),
            new Production([new Terminal('2147483647')]),
        ]);
        $ruleMap['safe_large_object_oid_list'] = new ProductionRule('safe_large_object_oid_list', [
            new Production([new NonTerminal('safe_large_object_oid')]),
            new Production([
                new NonTerminal('safe_large_object_oid_list'),
                new Terminal(','),
                new NonTerminal('safe_large_object_oid'),
            ]),
        ]);
        $ruleMap['privilege_target'] = new ProductionRule('privilege_target', array_map(
            static function (Production $alt): Production {
                $symbols = $alt->symbols;
                $names = array_map(self::symbolValue(...), $symbols);

                if ($names === ['LARGE_P', 'OBJECT_P', 'NumericOnly_list']) {
                    return new Production([
                        new Terminal('LARGE_P'),
                        new Terminal('OBJECT_P'),
                        new NonTerminal('safe_large_object_oid_list'),
                    ]);
                }

                return $alt;
            },
            $privilegeTarget->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentPartitionSpecRule(array $ruleMap): array
    {
        if (!isset($ruleMap['PartitionSpec'])) {
            return $ruleMap;
        }

        $ruleMap['safe_partition_strategy'] = new ProductionRule('safe_partition_strategy', [
            new Production([new Terminal('RANGE')]),
            new Production([new Terminal('LIST')]),
            new Production([new Terminal('HASH')]),
        ]);
        $ruleMap['PartitionSpec'] = new ProductionRule('PartitionSpec', [
            new Production([
                new Terminal('PARTITION'),
                new Terminal('BY'),
                new NonTerminal('safe_partition_strategy'),
                new Terminal('('),
                new NonTerminal('part_params'),
                new Terminal(')'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterOperatorDefinitionRule(array $ruleMap): array
    {
        $rule = $ruleMap['operator_def_elem'] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $rule->alternatives,
            static function (Production $alt): bool {
                return !(count($alt->symbols) === 1
                    && $alt->symbols[0] instanceof NonTerminal
                    && $alt->symbols[0]->value === 'ColLabel');
            },
        ));

        if ($filtered !== []) {
            $ruleMap['operator_def_elem'] = new ProductionRule('operator_def_elem', $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterOperatorArgTypesRule(array $ruleMap): array
    {
        $rule = $ruleMap['oper_argtypes'] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $rule->alternatives,
            static function (Production $alt): bool {
                return !(count($alt->symbols) === 3
                    && $alt->symbols[0] instanceof Terminal
                    && $alt->symbols[0]->value === '('
                    && $alt->symbols[1] instanceof NonTerminal
                    && $alt->symbols[1]->value === 'Typename'
                    && $alt->symbols[2] instanceof Terminal
                    && $alt->symbols[2]->value === ')');
            },
        ));

        if ($filtered !== []) {
            $ruleMap['oper_argtypes'] = new ProductionRule('oper_argtypes', $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterPublicationObjectSpecRule(array $ruleMap): array
    {
        $rule = $ruleMap['PublicationObjSpec'] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $rule->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    self::symbolValue(...),
                    $alt->symbols,
                );

                return $names === ['TABLE', 'relation_expr', 'opt_column_list', 'OptWhereClause']
                    || $names === ['TABLES', 'IN_P', 'SCHEMA', 'ColId'];
            },
        ));

        if ($filtered !== []) {
            $ruleMap['PublicationObjSpec'] = new ProductionRule('PublicationObjSpec', $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentPublicationRule(array $ruleMap): array
    {
        if (isset($ruleMap['opt_definition'])) {
            $ruleMap['opt_definition'] = new ProductionRule('opt_definition', [
                new Production([]),
            ]);
        }

        if (isset($ruleMap['pub_obj_list'])) {
            $ruleMap['pub_obj_list'] = new ProductionRule('pub_obj_list', [
                new Production([new NonTerminal('PublicationObjSpec')]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentTextSearchTemplateRule(array $ruleMap): array
    {
        $rule = $ruleMap['DefineStmt'] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $ruleMap['text_search_template_init_option'] = new ProductionRule('text_search_template_init_option', [
            new Production([new Terminal('INIT'), new Terminal('='), new NonTerminal('func_name')]),
        ]);
        $ruleMap['text_search_template_lexize_option'] = new ProductionRule('text_search_template_lexize_option', [
            new Production([new Terminal('LEXIZE'), new Terminal('='), new NonTerminal('func_name')]),
        ]);
        $ruleMap['text_search_template_definition'] = new ProductionRule('text_search_template_definition', [
            new Production([
                new Terminal('('),
                new NonTerminal('text_search_template_lexize_option'),
                new Terminal(')'),
            ]),
            new Production([
                new Terminal('('),
                new NonTerminal('text_search_template_init_option'),
                new Terminal(','),
                new NonTerminal('text_search_template_lexize_option'),
                new Terminal(')'),
            ]),
            new Production([
                new Terminal('('),
                new NonTerminal('text_search_template_lexize_option'),
                new Terminal(','),
                new NonTerminal('text_search_template_init_option'),
                new Terminal(')'),
            ]),
        ]);
        $ruleMap['DefineTextSearchTemplateStmt'] = new ProductionRule('DefineTextSearchTemplateStmt', [
            new Production([
                new Terminal('CREATE'),
                new Terminal('TEXT_P'),
                new Terminal('SEARCH'),
                new Terminal('TEMPLATE'),
                new NonTerminal('any_name'),
                new NonTerminal('text_search_template_definition'),
            ]),
        ]);
        $ruleMap['DefineStmt'] = new ProductionRule('DefineStmt', array_map(
            static function (Production $alt): Production {
                $names = array_map(
                    self::symbolValue(...),
                    $alt->symbols,
                );

                if ($names === ['CREATE', 'TEXT_P', 'SEARCH', 'TEMPLATE', 'any_name', 'definition']) {
                    return new Production([new NonTerminal('DefineTextSearchTemplateStmt')]);
                }

                return $alt;
            },
            $rule->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentDefineOperatorRule(array $ruleMap): array
    {
        $rule = $ruleMap['DefineStmt'] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $ruleMap['safe_operator_definition'] = new ProductionRule('safe_operator_definition', [
            new Production([
                new Terminal('('),
                new Terminal('PROCEDURE'),
                new Terminal('='),
                new NonTerminal('func_name'),
                new Terminal(','),
                new Terminal('LEFTARG'),
                new Terminal('='),
                new NonTerminal('safe_type_reference'),
                new Terminal(','),
                new Terminal('RIGHTARG'),
                new Terminal('='),
                new NonTerminal('safe_type_reference'),
                new Terminal(')'),
            ]),
            new Production([
                new Terminal('('),
                new Terminal('PROCEDURE'),
                new Terminal('='),
                new NonTerminal('func_name'),
                new Terminal(','),
                new Terminal('LEFTARG'),
                new Terminal('='),
                new Terminal('NONE'),
                new Terminal(','),
                new Terminal('RIGHTARG'),
                new Terminal('='),
                new NonTerminal('safe_type_reference'),
                new Terminal(')'),
            ]),
            new Production([
                new Terminal('('),
                new Terminal('PROCEDURE'),
                new Terminal('='),
                new NonTerminal('func_name'),
                new Terminal(','),
                new Terminal('LEFTARG'),
                new Terminal('='),
                new NonTerminal('safe_type_reference'),
                new Terminal(','),
                new Terminal('RIGHTARG'),
                new Terminal('='),
                new Terminal('NONE'),
                new Terminal(')'),
            ]),
        ]);
        $ruleMap['DefineOperatorStmt'] = new ProductionRule('DefineOperatorStmt', [
            new Production([
                new Terminal('CREATE'),
                new Terminal('OPERATOR'),
                new NonTerminal('any_operator'),
                new NonTerminal('safe_operator_definition'),
            ]),
        ]);
        $ruleMap['DefineStmt'] = new ProductionRule('DefineStmt', array_map(
            static function (Production $alt): Production {
                $names = array_map(
                    self::symbolValue(...),
                    $alt->symbols,
                );

                if ($names === ['CREATE', 'OPERATOR', 'any_operator', 'definition']) {
                    return new Production([new NonTerminal('DefineOperatorStmt')]);
                }

                return $alt;
            },
            $rule->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentDefineAggregateRule(array $ruleMap): array
    {
        $rule = $ruleMap['DefineStmt'] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $ruleMap['safe_aggregate_sfunc_option'] = new ProductionRule('safe_aggregate_sfunc_option', [
            new Production([new Terminal('SFUNC'), new Terminal('='), new NonTerminal('func_name')]),
        ]);
        $ruleMap['safe_aggregate_stype_option'] = new ProductionRule('safe_aggregate_stype_option', [
            new Production([new Terminal('STYPE'), new Terminal('='), new NonTerminal('safe_type_reference')]),
        ]);
        $ruleMap['safe_aggregate_definition'] = new ProductionRule('safe_aggregate_definition', [
            new Production([
                new Terminal('('),
                new NonTerminal('safe_aggregate_sfunc_option'),
                new Terminal(','),
                new NonTerminal('safe_aggregate_stype_option'),
                new Terminal(')'),
            ]),
            new Production([
                new Terminal('('),
                new NonTerminal('safe_aggregate_stype_option'),
                new Terminal(','),
                new NonTerminal('safe_aggregate_sfunc_option'),
                new Terminal(')'),
            ]),
        ]);
        $ruleMap['DefineAggregateStmt'] = new ProductionRule('DefineAggregateStmt', [
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('opt_or_replace'),
                new Terminal('AGGREGATE'),
                new NonTerminal('func_name'),
                new NonTerminal('aggr_args'),
                new NonTerminal('safe_aggregate_definition'),
            ]),
        ]);
        $ruleMap['DefineStmt'] = new ProductionRule('DefineStmt', array_map(
            static function (Production $alt): Production {
                $names = array_map(
                    self::symbolValue(...),
                    $alt->symbols,
                );

                if ($names === ['CREATE', 'opt_or_replace', 'AGGREGATE', 'func_name', 'aggr_args', 'definition']
                    || $names === ['CREATE', 'opt_or_replace', 'AGGREGATE', 'func_name', 'old_aggr_definition']) {
                    return new Production([new NonTerminal('DefineAggregateStmt')]);
                }

                return $alt;
            },
            $rule->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCommentRule(array $ruleMap): array
    {
        if (!isset($ruleMap['CommentStmt'])) {
            return $ruleMap;
        }

        $ruleMap['CommentTypeReferenceStmt'] = new ProductionRule('CommentTypeReferenceStmt', [
            new Production([
                new Terminal('COMMENT'),
                new Terminal('ON'),
                new Terminal('TYPE_P'),
                new NonTerminal('any_name'),
                new Terminal('IS'),
                new NonTerminal('comment_text'),
            ]),
            new Production([
                new Terminal('COMMENT'),
                new Terminal('ON'),
                new Terminal('DOMAIN_P'),
                new NonTerminal('any_name'),
                new Terminal('IS'),
                new NonTerminal('comment_text'),
            ]),
            new Production([
                new Terminal('COMMENT'),
                new Terminal('ON'),
                new Terminal('CAST'),
                new NonTerminal('safe_cast_signature'),
                new Terminal('IS'),
                new NonTerminal('comment_text'),
            ]),
            new Production([
                new Terminal('COMMENT'),
                new Terminal('ON'),
                new Terminal('TRANSFORM'),
                new Terminal('FOR'),
                new NonTerminal('safe_type_reference'),
                new Terminal('LANGUAGE'),
                new NonTerminal('name'),
                new Terminal('IS'),
                new NonTerminal('comment_text'),
            ]),
        ]);
        $ruleMap['CommentStmt'] = new ProductionRule('CommentStmt', array_map(
            static function (Production $alt): Production {
                $names = array_map(self::symbolValue(...), $alt->symbols);

                if ($names === ['COMMENT', 'ON', 'TYPE_P', 'Typename', 'IS', 'comment_text']
                    || $names === ['COMMENT', 'ON', 'DOMAIN_P', 'Typename', 'IS', 'comment_text']
                    || $names === ['COMMENT', 'ON', 'CAST', '(', 'Typename', 'AS', 'Typename', ')', 'IS', 'comment_text']
                    || $names === ['COMMENT', 'ON', 'TRANSFORM', 'FOR', 'Typename', 'LANGUAGE', 'name', 'IS', 'comment_text']) {
                    return new Production([new NonTerminal('CommentTypeReferenceStmt')]);
                }

                return $alt;
            },
            $ruleMap['CommentStmt']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCreateCastRule(array $ruleMap): array
    {
        if (!isset($ruleMap['CreateCastStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_cast_function_with_argtypes'] = new ProductionRule('safe_cast_function_with_argtypes', [
            new Production([
                new NonTerminal('func_name'),
                new Terminal('('),
                new NonTerminal('safe_type_reference'),
                new Terminal(')'),
            ]),
        ]);
        $ruleMap['CreateCastStmt'] = new ProductionRule('CreateCastStmt', [
            new Production([
                new Terminal('CREATE'),
                new Terminal('CAST'),
                new NonTerminal('safe_cast_signature'),
                new Terminal('WITH'),
                new Terminal('FUNCTION'),
                new NonTerminal('safe_cast_function_with_argtypes'),
                new NonTerminal('cast_context'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new Terminal('CAST'),
                new NonTerminal('safe_cast_signature'),
                new Terminal('WITHOUT'),
                new Terminal('FUNCTION'),
                new NonTerminal('cast_context'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new Terminal('CAST'),
                new NonTerminal('safe_cast_signature'),
                new Terminal('WITH'),
                new Terminal('INOUT'),
                new NonTerminal('cast_context'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentDropCastRule(array $ruleMap): array
    {
        if (!isset($ruleMap['DropCastStmt'])) {
            return $ruleMap;
        }

        $ruleMap['DropCastStmt'] = new ProductionRule('DropCastStmt', [
            new Production([
                new Terminal('DROP'),
                new Terminal('CAST'),
                new NonTerminal('opt_if_exists'),
                new NonTerminal('safe_cast_signature'),
                new NonTerminal('opt_drop_behavior'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCreateAssertionRule(array $ruleMap): array
    {
        if (!isset($ruleMap['CreateAssertionStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_assertion_check_expr'] = new ProductionRule('safe_assertion_check_expr', [
            new Production([new Terminal('TRUE_P')]),
            new Production([new Terminal('FALSE_P')]),
            new Production([new Terminal('ICONST'), new Terminal('='), new Terminal('ICONST')]),
            new Production([new Terminal('ICONST'), new Terminal('NOT_EQUALS'), new Terminal('ICONST')]),
            new Production([new Terminal('SCONST'), new Terminal('IS'), new Terminal('NOT'), new Terminal('NULL_P')]),
        ]);
        $ruleMap['CreateAssertionStmt'] = new ProductionRule('CreateAssertionStmt', [
            new Production([
                new Terminal('CREATE'),
                new Terminal('ASSERTION'),
                new NonTerminal('any_name'),
                new Terminal('CHECK'),
                new Terminal('('),
                new NonTerminal('safe_assertion_check_expr'),
                new Terminal(')'),
                new NonTerminal('ConstraintAttributeSpec'),
            ]),
        ]);

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
    private function augmentTriggerRule(array $ruleMap): array
    {
        if (!isset($ruleMap['TriggerEvents'])) {
            return $ruleMap;
        }

        $ruleMap['TriggerEvents'] = new ProductionRule('TriggerEvents', [
            new Production([new NonTerminal('TriggerOneEvent')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentTargetElementRule(array $ruleMap): array
    {
        $rule = $ruleMap['target_el'] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $rule->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;

                return !$first instanceof Terminal || $first->value !== '*';
            },
        ));

        if ($filtered !== []) {
            $ruleMap['target_el'] = new ProductionRule('target_el', $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentSelectRules(array $ruleMap): array
    {
        if (!isset($ruleMap['select_no_parens'], $ruleMap['select_with_parens'], $ruleMap['simple_select'], $ruleMap['a_expr'], $ruleMap['distinct_clause'])) {
            return $ruleMap;
        }

        $ruleMap['safe_select_a_expr'] = new ProductionRule('safe_select_a_expr', array_values(array_filter(
            $ruleMap['a_expr']->alternatives,
            static function (Production $alt): bool {
                $names = array_map(
                    self::symbolValue(...),
                    $alt->symbols,
                );

                return $names !== ['DEFAULT'];
            },
        )));
        $ruleMap['select_expr_list'] = new ProductionRule('select_expr_list', [
            new Production([new NonTerminal('safe_select_a_expr')]),
            new Production([new NonTerminal('select_expr_list'), new Terminal(','), new NonTerminal('safe_select_a_expr')]),
        ]);
        $ruleMap['safe_select_value_expr'] = new ProductionRule('safe_select_value_expr', [
            new Production([new Terminal('ICONST')]),
            new Production([new Terminal('NULL_P')]),
        ]);
        $ruleMap['safe_distinct_on_expr'] = new ProductionRule('safe_distinct_on_expr', [
            new Production([new NonTerminal('ColId')]),
            new Production([new NonTerminal('AexprConst')]),
        ]);
        $ruleMap['safe_distinct_on_expr_list'] = new ProductionRule('safe_distinct_on_expr_list', [
            new Production([new NonTerminal('safe_distinct_on_expr')]),
            new Production([new NonTerminal('safe_distinct_on_expr_list'), new Terminal(','), new NonTerminal('safe_distinct_on_expr')]),
        ]);
        $ruleMap['distinct_clause'] = new ProductionRule('distinct_clause', [
            new Production([new Terminal('DISTINCT')]),
            new Production([new Terminal('DISTINCT'), new Terminal('ON'), new Terminal('('), new NonTerminal('safe_distinct_on_expr_list'), new Terminal(')')]),
        ]);
        $ruleMap['select_values_clause'] = new ProductionRule('select_values_clause', []);

        foreach (range(1, self::CTAS_ARITY_LIMIT) as $arity) {
            $exprListRule = sprintf('select_value_expr_list_%d', $arity);
            $rowRule = sprintf('select_value_row_%d', $arity);
            $rowListRule = sprintf('select_value_row_list_%d', $arity);
            $valuesRule = sprintf('select_values_clause_%d', $arity);

            $ruleMap[$exprListRule] = new ProductionRule($exprListRule, [
                new Production($this->buildCommaSeparatedRuleList('safe_select_value_expr', $arity)),
            ]);
            $ruleMap[$rowRule] = new ProductionRule($rowRule, [
                new Production([
                    new Terminal('('),
                    new NonTerminal($exprListRule),
                    new Terminal(')'),
                ]),
            ]);
            $ruleMap[$rowListRule] = new ProductionRule($rowListRule, [
                new Production([new NonTerminal($rowRule)]),
                new Production([new NonTerminal($rowListRule), new Terminal(','), new NonTerminal($rowRule)]),
            ]);
            $ruleMap[$valuesRule] = new ProductionRule($valuesRule, [
                new Production([
                    new Terminal('VALUES'),
                    new NonTerminal($rowListRule),
                ]),
            ]);
            $ruleMap['select_values_clause'] = new ProductionRule('select_values_clause', [
                ...$ruleMap['select_values_clause']->alternatives,
                new Production([new NonTerminal($valuesRule)]),
            ]);
        }

        $ruleMap['setop_target_el'] = new ProductionRule('setop_target_el', [
            new Production([new NonTerminal('AexprConst')]),
            new Production([new NonTerminal('AexprConst'), new Terminal('AS'), new NonTerminal('ColLabel')]),
        ]);
        $ruleMap['set_operation_select_stmt'] = new ProductionRule('set_operation_select_stmt', []);

        foreach (range(1, self::CTAS_ARITY_LIMIT) as $arity) {
            $targetRule = sprintf('setop_target_list_%d', $arity);
            $leafRule = sprintf('setop_leaf_select_%d', $arity);
            $operandRule = sprintf('setop_select_operand_%d', $arity);
            $stmtRule = sprintf('setop_select_stmt_%d', $arity);

            $ruleMap[$targetRule] = new ProductionRule($targetRule, [
                new Production($this->buildCommaSeparatedRuleList('setop_target_el', $arity)),
            ]);
            $ruleMap[$leafRule] = new ProductionRule($leafRule, [
                new Production([
                    new Terminal('SELECT'),
                    new NonTerminal($targetRule),
                ]),
            ]);
            $ruleMap[$operandRule] = new ProductionRule($operandRule, [
                new Production([new NonTerminal($leafRule)]),
                new Production([
                    new Terminal('('),
                    new NonTerminal($leafRule),
                    new Terminal(')'),
                ]),
            ]);
            $ruleMap[$stmtRule] = new ProductionRule($stmtRule, [
                new Production([
                    new NonTerminal($operandRule),
                    new Terminal('UNION'),
                    new NonTerminal('set_quantifier'),
                    new NonTerminal($operandRule),
                ]),
                new Production([
                    new NonTerminal($operandRule),
                    new Terminal('INTERSECT'),
                    new NonTerminal('set_quantifier'),
                    new NonTerminal($operandRule),
                ]),
                new Production([
                    new NonTerminal($operandRule),
                    new Terminal('EXCEPT'),
                    new NonTerminal('set_quantifier'),
                    new NonTerminal($operandRule),
                ]),
            ]);
            $ruleMap['set_operation_select_stmt'] = new ProductionRule('set_operation_select_stmt', [
                ...$ruleMap['set_operation_select_stmt']->alternatives,
                new Production([new NonTerminal($stmtRule)]),
            ]);
        }

        $ruleMap['simple_select'] = new ProductionRule('simple_select', array_merge(
            array_map(
                static function (Production $alt): Production {
                    $names = array_map(
                        self::symbolValue(...),
                        $alt->symbols,
                    );

                    if ($names === ['values_clause']) {
                        return new Production([new NonTerminal('select_values_clause')]);
                    }

                    if ($names === ['SELECT', 'opt_all_clause', 'opt_target_list', 'into_clause', 'from_clause', 'where_clause', 'group_clause', 'having_clause', 'window_clause']) {
                        return new Production([
                            new Terminal('SELECT'),
                            new NonTerminal('opt_all_clause'),
                            new NonTerminal('target_list'),
                            new NonTerminal('into_clause'),
                            new NonTerminal('from_clause'),
                            new NonTerminal('where_clause'),
                            new NonTerminal('group_clause'),
                            new NonTerminal('having_clause'),
                            new NonTerminal('window_clause'),
                        ]);
                    }

                    return $alt;
                },
                array_values(array_filter(
                    $ruleMap['simple_select']->alternatives,
                    static function (Production $alt): bool {
                        $names = array_map(
                            self::symbolValue(...),
                            $alt->symbols,
                        );

                        return $names !== ['select_clause', 'UNION', 'set_quantifier', 'select_clause']
                            && $names !== ['select_clause', 'INTERSECT', 'set_quantifier', 'select_clause']
                            && $names !== ['select_clause', 'EXCEPT', 'set_quantifier', 'select_clause'];
                    },
                )),
            ),
            [
                new Production([new NonTerminal('set_operation_select_stmt')]),
            ],
        ));
        $ruleMap['select_core'] = new ProductionRule('select_core', [
            new Production([new NonTerminal('simple_select')]),
            new Production([new NonTerminal('with_clause'), new NonTerminal('simple_select')]),
        ]);
        $ruleMap['select_no_parens'] = new ProductionRule('select_no_parens', [
            new Production([new NonTerminal('select_core')]),
            new Production([new NonTerminal('select_core'), new NonTerminal('sort_clause')]),
            new Production([new NonTerminal('select_core'), new NonTerminal('opt_sort_clause'), new NonTerminal('for_locking_clause'), new NonTerminal('opt_select_limit')]),
            new Production([new NonTerminal('select_core'), new NonTerminal('opt_sort_clause'), new NonTerminal('select_limit'), new NonTerminal('opt_for_locking_clause')]),
        ]);
        $ruleMap['select_with_parens'] = new ProductionRule('select_with_parens', [
            new Production([new Terminal('('), new NonTerminal('select_core'), new Terminal(')')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCreateAsRule(array $ruleMap): array
    {
        if (!isset($ruleMap['CreateAsStmt'])) {
            return $ruleMap;
        }

        $ruleMap = $this->ensureTemporaryRelationRules($ruleMap);

        $ruleMap['create_as_target_no_columns_non_temp'] = new ProductionRule('create_as_target_no_columns_non_temp', [
            new Production([
                new NonTerminal('qualified_name'),
                new NonTerminal('table_access_method_clause'),
                new NonTerminal('OptWith'),
                new NonTerminal('OnCommitOption'),
                new NonTerminal('OptTableSpace'),
            ]),
        ]);
        $ruleMap['create_as_target_no_columns_temp'] = new ProductionRule('create_as_target_no_columns_temp', [
            new Production([
                new NonTerminal('safe_temporary_relation_name'),
                new NonTerminal('table_access_method_clause'),
                new NonTerminal('OptWith'),
                new NonTerminal('OnCommitOption'),
                new NonTerminal('OptTableSpace'),
            ]),
        ]);
        $ruleMap['execute_create_as_target_non_temp'] = new ProductionRule('execute_create_as_target_non_temp', [
            new Production([
                new NonTerminal('qualified_name'),
                new NonTerminal('opt_column_list'),
                new NonTerminal('table_access_method_clause'),
                new NonTerminal('OptWith'),
                new NonTerminal('OnCommitOption'),
                new NonTerminal('OptTableSpace'),
            ]),
        ]);
        $ruleMap['execute_create_as_target_temp'] = new ProductionRule('execute_create_as_target_temp', [
            new Production([
                new NonTerminal('safe_temporary_relation_name'),
                new NonTerminal('opt_column_list'),
                new NonTerminal('table_access_method_clause'),
                new NonTerminal('OptWith'),
                new NonTerminal('OnCommitOption'),
                new NonTerminal('OptTableSpace'),
            ]),
        ]);
        $ruleMap['ctas_target_el'] = new ProductionRule('ctas_target_el', [
            new Production([new NonTerminal('AexprConst')]),
            new Production([new NonTerminal('AexprConst'), new Terminal('AS'), new NonTerminal('ColLabel')]),
        ]);

        $alternatives = [
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_table_non_temp_modifier'),
                new Terminal('TABLE'),
                new NonTerminal('create_as_target_no_columns_non_temp'),
                new Terminal('AS'),
                new NonTerminal('SelectStmt'),
                new NonTerminal('opt_with_data'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_temporary_relation_modifier'),
                new Terminal('TABLE'),
                new NonTerminal('create_as_target_no_columns_temp'),
                new Terminal('AS'),
                new NonTerminal('SelectStmt'),
                new NonTerminal('opt_with_data'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_table_non_temp_modifier'),
                new Terminal('TABLE'),
                new Terminal('IF_P'),
                new Terminal('NOT'),
                new Terminal('EXISTS'),
                new NonTerminal('create_as_target_no_columns_non_temp'),
                new Terminal('AS'),
                new NonTerminal('SelectStmt'),
                new NonTerminal('opt_with_data'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_temporary_relation_modifier'),
                new Terminal('TABLE'),
                new Terminal('IF_P'),
                new Terminal('NOT'),
                new Terminal('EXISTS'),
                new NonTerminal('create_as_target_no_columns_temp'),
                new Terminal('AS'),
                new NonTerminal('SelectStmt'),
                new NonTerminal('opt_with_data'),
            ]),
        ];

        foreach (range(1, self::CTAS_ARITY_LIMIT) as $arity) {
            $columnRule = sprintf('ctas_column_list_%d', $arity);
            $targetRule = sprintf('ctas_target_list_%d', $arity);
            $selectRule = sprintf('ctas_select_stmt_%d', $arity);
            $createTargetNonTempRule = sprintf('create_as_target_non_temp_%d', $arity);
            $createTargetTempRule = sprintf('create_as_target_temp_%d', $arity);

            $ruleMap[$columnRule] = new ProductionRule($columnRule, [
                new Production($this->wrapInParens($this->buildCommaSeparatedRuleList('columnElem', $arity))),
            ]);
            $ruleMap[$targetRule] = new ProductionRule($targetRule, [
                new Production($this->buildCommaSeparatedRuleList('ctas_target_el', $arity)),
            ]);
            $ruleMap[$selectRule] = new ProductionRule($selectRule, [
                new Production([
                    new Terminal('SELECT'),
                    new NonTerminal($targetRule),
                ]),
            ]);
            $ruleMap[$createTargetNonTempRule] = new ProductionRule($createTargetNonTempRule, [
                new Production([
                    new NonTerminal('qualified_name'),
                    new NonTerminal($columnRule),
                    new NonTerminal('table_access_method_clause'),
                    new NonTerminal('OptWith'),
                    new NonTerminal('OnCommitOption'),
                    new NonTerminal('OptTableSpace'),
                ]),
            ]);
            $ruleMap[$createTargetTempRule] = new ProductionRule($createTargetTempRule, [
                new Production([
                    new NonTerminal('safe_temporary_relation_name'),
                    new NonTerminal($columnRule),
                    new NonTerminal('table_access_method_clause'),
                    new NonTerminal('OptWith'),
                    new NonTerminal('OnCommitOption'),
                    new NonTerminal('OptTableSpace'),
                ]),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_table_non_temp_modifier'),
                new Terminal('TABLE'),
                new NonTerminal($createTargetNonTempRule),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_with_data'),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_temporary_relation_modifier'),
                new Terminal('TABLE'),
                new NonTerminal($createTargetTempRule),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_with_data'),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_table_non_temp_modifier'),
                new Terminal('TABLE'),
                new Terminal('IF_P'),
                new Terminal('NOT'),
                new Terminal('EXISTS'),
                new NonTerminal($createTargetNonTempRule),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_with_data'),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_temporary_relation_modifier'),
                new Terminal('TABLE'),
                new Terminal('IF_P'),
                new Terminal('NOT'),
                new Terminal('EXISTS'),
                new NonTerminal($createTargetTempRule),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_with_data'),
            ]);
        }

        $ruleMap['CreateAsStmt'] = new ProductionRule('CreateAsStmt', $alternatives);
        if (isset($ruleMap['ExecuteStmt'])) {
            $ruleMap['ExecuteStmt'] = new ProductionRule('ExecuteStmt', [
                new Production([new Terminal('EXECUTE'), new NonTerminal('name'), new NonTerminal('execute_param_clause')]),
                new Production([
                    new Terminal('CREATE'),
                    new NonTerminal('safe_table_non_temp_modifier'),
                    new Terminal('TABLE'),
                    new NonTerminal('execute_create_as_target_non_temp'),
                    new Terminal('AS'),
                    new Terminal('EXECUTE'),
                    new NonTerminal('name'),
                    new NonTerminal('execute_param_clause'),
                    new NonTerminal('opt_with_data'),
                ]),
                new Production([
                    new Terminal('CREATE'),
                    new NonTerminal('safe_temporary_relation_modifier'),
                    new Terminal('TABLE'),
                    new NonTerminal('execute_create_as_target_temp'),
                    new Terminal('AS'),
                    new Terminal('EXECUTE'),
                    new NonTerminal('name'),
                    new NonTerminal('execute_param_clause'),
                    new NonTerminal('opt_with_data'),
                ]),
                new Production([
                    new Terminal('CREATE'),
                    new NonTerminal('safe_table_non_temp_modifier'),
                    new Terminal('TABLE'),
                    new Terminal('IF_P'),
                    new Terminal('NOT'),
                    new Terminal('EXISTS'),
                    new NonTerminal('execute_create_as_target_non_temp'),
                    new Terminal('AS'),
                    new Terminal('EXECUTE'),
                    new NonTerminal('name'),
                    new NonTerminal('execute_param_clause'),
                    new NonTerminal('opt_with_data'),
                ]),
                new Production([
                    new Terminal('CREATE'),
                    new NonTerminal('safe_temporary_relation_modifier'),
                    new Terminal('TABLE'),
                    new Terminal('IF_P'),
                    new Terminal('NOT'),
                    new Terminal('EXISTS'),
                    new NonTerminal('execute_create_as_target_temp'),
                    new Terminal('AS'),
                    new Terminal('EXECUTE'),
                    new NonTerminal('name'),
                    new NonTerminal('execute_param_clause'),
                    new NonTerminal('opt_with_data'),
                ]),
            ]);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentViewRule(array $ruleMap): array
    {
        if (!isset($ruleMap['ViewStmt'])) {
            return $ruleMap;
        }

        $ruleMap = $this->ensureTemporaryRelationRules($ruleMap);
        $ruleMap['view_target_el'] = new ProductionRule('view_target_el', [
            new Production([new NonTerminal('AexprConst')]),
            new Production([new NonTerminal('AexprConst'), new Terminal('AS'), new NonTerminal('ColLabel')]),
        ]);

        $alternatives = [
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_view_non_temp_modifier'),
                new Terminal('VIEW'),
                new NonTerminal('qualified_name'),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal('SelectStmt'),
                new NonTerminal('opt_check_option'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_temporary_relation_modifier'),
                new Terminal('VIEW'),
                new NonTerminal('safe_temporary_relation_name'),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal('SelectStmt'),
                new NonTerminal('opt_check_option'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new Terminal('OR'),
                new Terminal('REPLACE'),
                new NonTerminal('safe_view_non_temp_modifier'),
                new Terminal('VIEW'),
                new NonTerminal('qualified_name'),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal('SelectStmt'),
                new NonTerminal('opt_check_option'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new Terminal('OR'),
                new Terminal('REPLACE'),
                new NonTerminal('safe_temporary_relation_modifier'),
                new Terminal('VIEW'),
                new NonTerminal('safe_temporary_relation_name'),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal('SelectStmt'),
                new NonTerminal('opt_check_option'),
            ]),
        ];

        foreach (range(1, self::CTAS_ARITY_LIMIT) as $arity) {
            $columnRule = sprintf('view_column_list_%d', $arity);
            $targetRule = sprintf('view_target_list_%d', $arity);
            $selectRule = sprintf('view_select_stmt_%d', $arity);

            $ruleMap[$columnRule] = new ProductionRule($columnRule, [
                new Production($this->wrapInParens($this->buildCommaSeparatedRuleList('columnElem', $arity))),
            ]);
            $ruleMap[$targetRule] = new ProductionRule($targetRule, [
                new Production($this->buildCommaSeparatedRuleList('view_target_el', $arity)),
            ]);
            $ruleMap[$selectRule] = new ProductionRule($selectRule, [
                new Production([
                    new Terminal('SELECT'),
                    new NonTerminal($targetRule),
                ]),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_view_non_temp_modifier'),
                new Terminal('VIEW'),
                new NonTerminal('qualified_name'),
                new NonTerminal($columnRule),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_check_option'),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_temporary_relation_modifier'),
                new Terminal('VIEW'),
                new NonTerminal('safe_temporary_relation_name'),
                new NonTerminal($columnRule),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_check_option'),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new Terminal('OR'),
                new Terminal('REPLACE'),
                new NonTerminal('safe_view_non_temp_modifier'),
                new Terminal('VIEW'),
                new NonTerminal('qualified_name'),
                new NonTerminal($columnRule),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_check_option'),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new Terminal('OR'),
                new Terminal('REPLACE'),
                new NonTerminal('safe_temporary_relation_modifier'),
                new Terminal('VIEW'),
                new NonTerminal('safe_temporary_relation_name'),
                new NonTerminal($columnRule),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_check_option'),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_view_non_temp_modifier'),
                new Terminal('RECURSIVE'),
                new Terminal('VIEW'),
                new NonTerminal('qualified_name'),
                new NonTerminal($columnRule),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_check_option'),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new Terminal('OR'),
                new Terminal('REPLACE'),
                new NonTerminal('safe_view_non_temp_modifier'),
                new Terminal('RECURSIVE'),
                new Terminal('VIEW'),
                new NonTerminal('qualified_name'),
                new NonTerminal($columnRule),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_check_option'),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new NonTerminal('safe_temporary_relation_modifier'),
                new Terminal('RECURSIVE'),
                new Terminal('VIEW'),
                new NonTerminal('safe_temporary_relation_name'),
                new NonTerminal($columnRule),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_check_option'),
            ]);
            $alternatives[] = new Production([
                new Terminal('CREATE'),
                new Terminal('OR'),
                new Terminal('REPLACE'),
                new NonTerminal('safe_temporary_relation_modifier'),
                new Terminal('RECURSIVE'),
                new Terminal('VIEW'),
                new NonTerminal('safe_temporary_relation_name'),
                new NonTerminal($columnRule),
                new NonTerminal('opt_reloptions'),
                new Terminal('AS'),
                new NonTerminal($selectRule),
                new NonTerminal('opt_check_option'),
            ]);
        }

        $ruleMap['ViewStmt'] = new ProductionRule('ViewStmt', $alternatives);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentInsertRule(array $ruleMap): array
    {
        if (!isset($ruleMap['insert_rest']) || !isset($ruleMap['opt_on_conflict'])) {
            return $ruleMap;
        }

        $ruleMap['insert_target_el'] = new ProductionRule('insert_target_el', [
            new Production([new NonTerminal('AexprConst')]),
            new Production([new NonTerminal('AexprConst'), new Terminal('AS'), new NonTerminal('ColLabel')]),
        ]);
        $ruleMap['safe_conf_expr'] = new ProductionRule('safe_conf_expr', [
            new Production([new Terminal('('), new NonTerminal('index_params'), new Terminal(')'), new NonTerminal('where_clause')]),
            new Production([new Terminal('ON'), new Terminal('CONSTRAINT'), new NonTerminal('name')]),
        ]);
        $ruleMap['safe_insert_conflict_set_clause'] = new ProductionRule('safe_insert_conflict_set_clause', [
            new Production([
                new NonTerminal('set_target'),
                new Terminal('='),
                new NonTerminal('AexprConst'),
            ]),
        ]);
        $ruleMap['safe_insert_conflict_set_clause_list'] = new ProductionRule('safe_insert_conflict_set_clause_list', [
            new Production([new NonTerminal('safe_insert_conflict_set_clause')]),
        ]);
        $ruleMap['insert_conflict_update_clause'] = new ProductionRule('insert_conflict_update_clause', [
            new Production([
                new Terminal('ON'),
                new Terminal('CONFLICT'),
                new NonTerminal('safe_conf_expr'),
                new Terminal('DO'),
                new Terminal('UPDATE'),
                new Terminal('SET'),
                new NonTerminal('safe_insert_conflict_set_clause_list'),
                new NonTerminal('where_clause'),
            ]),
        ]);

        $insertRestAlternatives = [
            new Production([new NonTerminal('SelectStmt')]),
            new Production([new Terminal('OVERRIDING'), new NonTerminal('override_kind'), new Terminal('VALUE_P'), new NonTerminal('SelectStmt')]),
            new Production([new Terminal('DEFAULT'), new Terminal('VALUES')]),
        ];

        foreach (range(1, self::CTAS_ARITY_LIMIT) as $arity) {
            $columnRule = sprintf('insert_column_list_%d', $arity);
            $targetRule = sprintf('insert_target_list_%d', $arity);
            $selectRule = sprintf('insert_select_stmt_%d', $arity);

            $ruleMap[$columnRule] = new ProductionRule($columnRule, [
                new Production($this->buildCommaSeparatedRuleList('insert_column_item', $arity)),
            ]);
            $ruleMap[$targetRule] = new ProductionRule($targetRule, [
                new Production($this->buildCommaSeparatedRuleList('insert_target_el', $arity)),
            ]);
            $ruleMap[$selectRule] = new ProductionRule($selectRule, [
                new Production([
                    new Terminal('SELECT'),
                    new NonTerminal($targetRule),
                ]),
            ]);
            $insertRestAlternatives[] = new Production([
                new Terminal('('),
                new NonTerminal($columnRule),
                new Terminal(')'),
                new NonTerminal($selectRule),
            ]);
            $insertRestAlternatives[] = new Production([
                new Terminal('('),
                new NonTerminal($columnRule),
                new Terminal(')'),
                new Terminal('OVERRIDING'),
                new NonTerminal('override_kind'),
                new Terminal('VALUE_P'),
                new NonTerminal($selectRule),
            ]);
        }

        $ruleMap['insert_rest'] = new ProductionRule('insert_rest', $insertRestAlternatives);
        $ruleMap['opt_on_conflict'] = new ProductionRule('opt_on_conflict', [
            new Production([
                new Terminal('ON'),
                new Terminal('CONFLICT'),
                new NonTerminal('safe_conf_expr'),
                new Terminal('DO'),
                new Terminal('UPDATE'),
                new Terminal('SET'),
                new NonTerminal('safe_insert_conflict_set_clause_list'),
                new NonTerminal('where_clause'),
            ]),
            new Production([
                new Terminal('ON'),
                new Terminal('CONFLICT'),
                new NonTerminal('opt_conf_expr'),
                new Terminal('DO'),
                new Terminal('NOTHING'),
            ]),
            new Production([]),
        ]);
        $ruleMap['insert_conflict_update_stmt'] = new ProductionRule('insert_conflict_update_stmt', [
            new Production([
                new NonTerminal('opt_with_clause'),
                new Terminal('INSERT'),
                new Terminal('INTO'),
                new NonTerminal('insert_target'),
                new NonTerminal('insert_rest'),
                new NonTerminal('insert_conflict_update_clause'),
                new NonTerminal('returning_clause'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCteRule(array $ruleMap): array
    {
        if (!isset($ruleMap['cte_list'])) {
            return $ruleMap;
        }

        $ruleMap['cte_list'] = new ProductionRule('cte_list', [
            new Production([new NonTerminal('common_table_expr')]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCreateMaterializedViewRule(array $ruleMap): array
    {
        if (!isset($ruleMap['create_mv_target'])) {
            return $ruleMap;
        }

        $ruleMap['create_mv_target'] = new ProductionRule('create_mv_target', [
            new Production([
                new NonTerminal('qualified_name'),
                new NonTerminal('table_access_method_clause'),
                new NonTerminal('opt_reloptions'),
                new NonTerminal('OptTableSpace'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentMergeRule(array $ruleMap): array
    {
        if (!isset($ruleMap['MergeStmt'])) {
            return $ruleMap;
        }

        $ruleMap['MergeStmt'] = new ProductionRule('MergeStmt', [
            new Production([
                new Terminal('MERGE'),
                new Terminal('INTO'),
                new NonTerminal('relation_expr_opt_alias'),
                new Terminal('USING'),
                new NonTerminal('table_ref'),
                new Terminal('ON'),
                new NonTerminal('a_expr'),
                new NonTerminal('merge_when_list'),
                new NonTerminal('returning_clause'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentRevokeRoleRule(array $ruleMap): array
    {
        if (!isset($ruleMap['RevokeRoleStmt'])) {
            return $ruleMap;
        }

        $ruleMap['RevokeRoleStmt'] = new ProductionRule('RevokeRoleStmt', [
            new Production([
                new Terminal('REVOKE'),
                new NonTerminal('safe_role_name_list'),
                new Terminal('FROM'),
                new NonTerminal('safe_role_name_list'),
                new NonTerminal('opt_granted_by'),
                new NonTerminal('opt_drop_behavior'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentDoStmtRule(array $ruleMap): array
    {
        if (!isset($ruleMap['DoStmt'])) {
            return $ruleMap;
        }

        $ruleMap['DoStmt'] = new ProductionRule('DoStmt', [
            new Production([
                new Terminal('DO'),
                new Terminal('DO_BODY_SCONST'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCreateFunctionRule(array $ruleMap): array
    {
        if (!isset($ruleMap['CreateFunctionStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_create_routine_args'] = new ProductionRule('safe_create_routine_args', [
            new Production([new Terminal('('), new Terminal(')')]),
        ]);
        $ruleMap['safe_create_routine_return_type'] = new ProductionRule('safe_create_routine_return_type', [
            new Production([new Terminal('INT_P')]),
        ]);
        $ruleMap['safe_create_routine_sql_body_literal'] = new ProductionRule('safe_create_routine_sql_body_literal', [
            new Production([new Terminal("'SELECT 1'")]),
        ]);
        $ruleMap['safe_create_routine_sql_options'] = new ProductionRule('safe_create_routine_sql_options', [
            new Production([
                new Terminal('LANGUAGE'),
                new Terminal('SQL_P'),
                new Terminal('AS'),
                new NonTerminal('safe_create_routine_sql_body_literal'),
            ]),
            new Production([
                new Terminal('AS'),
                new NonTerminal('safe_create_routine_sql_body_literal'),
                new Terminal('LANGUAGE'),
                new Terminal('SQL_P'),
            ]),
        ]);
        $ruleMap['safe_create_routine_return_body'] = new ProductionRule('safe_create_routine_return_body', [
            new Production([new Terminal('RETURN'), new Terminal('1')]),
        ]);
        $ruleMap['CreateFunctionStmt'] = new ProductionRule('CreateFunctionStmt', [
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('opt_or_replace'),
                new Terminal('FUNCTION'),
                new NonTerminal('func_name'),
                new NonTerminal('safe_create_routine_args'),
                new Terminal('RETURNS'),
                new NonTerminal('safe_create_routine_return_type'),
                new NonTerminal('safe_create_routine_sql_options'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('opt_or_replace'),
                new Terminal('FUNCTION'),
                new NonTerminal('func_name'),
                new NonTerminal('safe_create_routine_args'),
                new Terminal('RETURNS'),
                new NonTerminal('safe_create_routine_return_type'),
                new NonTerminal('safe_create_routine_return_body'),
            ]),
            new Production([
                new Terminal('CREATE'),
                new NonTerminal('opt_or_replace'),
                new Terminal('PROCEDURE'),
                new NonTerminal('func_name'),
                new NonTerminal('safe_create_routine_args'),
                new NonTerminal('safe_create_routine_sql_options'),
            ]),
        ]);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentDropTypeRule(array $ruleMap): array
    {
        if (!isset($ruleMap['DropStmt'])) {
            return $ruleMap;
        }

        $ruleMap['safe_drop_type_name_list'] = new ProductionRule('safe_drop_type_name_list', [
            new Production([new NonTerminal('any_name')]),
            new Production([new NonTerminal('any_name'), new Terminal(','), new NonTerminal('any_name')]),
        ]);
        $ruleMap['DropStmt'] = new ProductionRule('DropStmt', array_map(
            static function (Production $alt): Production {
                $symbols = $alt->symbols;
                $names = array_map(self::symbolValue(...), $symbols);

                if ($names === ['DROP', 'TYPE_P', 'type_name_list', 'opt_drop_behavior']) {
                    $symbols[2] = new NonTerminal('safe_drop_type_name_list');

                    return new Production(array_values($symbols));
                }

                if ($names === ['DROP', 'TYPE_P', 'IF_P', 'EXISTS', 'type_name_list', 'opt_drop_behavior']) {
                    $symbols[4] = new NonTerminal('safe_drop_type_name_list');

                    return new Production(array_values($symbols));
                }

                if ($names === ['DROP', 'DOMAIN_P', 'type_name_list', 'opt_drop_behavior']) {
                    $symbols[2] = new NonTerminal('safe_drop_type_name_list');

                    return new Production(array_values($symbols));
                }

                if ($names === ['DROP', 'DOMAIN_P', 'IF_P', 'EXISTS', 'type_name_list', 'opt_drop_behavior']) {
                    $symbols[4] = new NonTerminal('safe_drop_type_name_list');

                    return new Production(array_values($symbols));
                }

                return $alt;
            },
            $ruleMap['DropStmt']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function ensureTemporaryRelationRules(array $ruleMap): array
    {
        $ruleMap['safe_temporary_relation_name'] = $ruleMap['safe_temporary_relation_name'] ?? new ProductionRule('safe_temporary_relation_name', [
            new Production([new NonTerminal('ColId')]),
        ]);
        $ruleMap['safe_temporary_relation_modifier'] = $ruleMap['safe_temporary_relation_modifier'] ?? new ProductionRule(
            'safe_temporary_relation_modifier',
            $this->temporaryRelationModifierAlternatives(),
        );
        $ruleMap['safe_table_non_temp_modifier'] = $ruleMap['safe_table_non_temp_modifier'] ?? new ProductionRule('safe_table_non_temp_modifier', [
            new Production([new Terminal('UNLOGGED')]),
            new Production([]),
        ]);
        $ruleMap['safe_view_non_temp_modifier'] = $ruleMap['safe_view_non_temp_modifier'] ?? new ProductionRule('safe_view_non_temp_modifier', [
            new Production([]),
        ]);
        $ruleMap['safe_sequence_non_temp_modifier'] = $ruleMap['safe_sequence_non_temp_modifier'] ?? new ProductionRule('safe_sequence_non_temp_modifier', [
            new Production([]),
        ]);

        return $ruleMap;
    }

    /**
     * @return list<Symbol>
     */
    private function buildCommaSeparatedRuleList(string $ruleName, int $arity): array
    {
        $symbols = [];

        foreach (range(1, $arity) as $index) {
            if ($index > 1) {
                $symbols[] = new Terminal(',');
            }

            $symbols[] = new NonTerminal($ruleName);
        }

        return $symbols;
    }

    /**
     * @param list<Symbol> $symbols
     * @return list<Symbol>
     */
    private function wrapInParens(array $symbols): array
    {
        return [
            new Terminal('('),
            ...$symbols,
            new Terminal(')'),
        ];
    }

    /**
     * @return list<Production>
     */
    private function canonicalQualifiedNameAlternatives(string $headRule): array
    {
        $relationAttrSuffixes = [
            [],
            [new Terminal('.'), new NonTerminal('attr_name')],
        ];

        return array_map(
            static fn (array $suffix): Production => new Production([new NonTerminal($headRule), ...$suffix]),
            $relationAttrSuffixes,
        );
    }

    /**
     * @return list<Production>
     */
    private function temporaryRelationModifierAlternatives(): array
    {
        return [
            new Production([new Terminal('TEMPORARY')]),
            new Production([new Terminal('TEMP')]),
            new Production([new Terminal('LOCAL'), new Terminal('TEMPORARY')]),
            new Production([new Terminal('LOCAL'), new Terminal('TEMP')]),
            new Production([new Terminal('GLOBAL'), new Terminal('TEMPORARY')]),
            new Production([new Terminal('GLOBAL'), new Terminal('TEMP')]),
        ];
    }

    /**
     * Generate a syntactically valid SQL string.
     *
     * @param string|null $startRule Grammar rule to start from (null for default)
     * @param int $targetDepth Depth at which generator starts seeking termination
     */
    public function generate(?string $startRule = null, int $targetDepth = PHP_INT_MAX): string
    {
        $this->derivationSteps = 0;
        $this->identifierOrdinal = 0;
        $this->targetDepth = max(1, $targetDepth);

        $start = $startRule ?? 'stmtmulti';

        $terminals = $this->derive($start);

        return $this->render($terminals);
    }

    public function compiledGrammar(): Grammar
    {
        return $this->grammar;
    }

    /**
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
     * Handles PostgreSQL-specific terminal resolution and spacing rules.
     *
     * @param list<Terminal> $terminals
     */
    private function render(array $terminals): string
    {
        $tokens = [];
        foreach ($terminals as $terminal) {
            $name = $terminal->value;

            $token = match ($name) {
                'MODE_TYPE_NAME', 'MODE_PLPGSQL_EXPR', 'MODE_PLPGSQL_ASSIGN1',
                'MODE_PLPGSQL_ASSIGN2', 'MODE_PLPGSQL_ASSIGN3' => null,

                'TYPECAST' => '::',
                'DOT_DOT' => '..',
                'COLON_EQUALS' => ':=',
                'EQUALS_GREATER' => '=>',
                'NOT_EQUALS' => '!=',
                'LESS_EQUALS' => '<=',
                'GREATER_EQUALS' => '>=',
                'NOT_LA' => 'NOT',
                'WITH_LA' => 'WITH',
                'WITHOUT_LA' => 'WITHOUT',
                'FORMAT_LA' => 'FORMAT',
                'NULLS_LA' => 'NULLS',

                'IDENT' => $this->nextCanonicalIdentifier(),
                'UIDENT' => sprintf('U&"%s"', $this->nextCanonicalIdentifier()),
                'SCONST' => $this->lexicalValues->stringLiteral(),
                'DO_BODY_SCONST' => $this->lexicalValues->doBodyLiteral(),
                'USCONST' => sprintf("U&'%s'", $this->rsg->mixedAlnumString()),
                'ICONST' => $this->lexicalValues->integerLiteral(),
                'FCONST' => $this->lexicalValues->decimalLiteral(),
                'BCONST' => $this->lexicalValues->binaryLiteral(),
                'XCONST' => $this->lexicalValues->hexLiteral(),
                'Op' => $this->generateOperator(),
                'PARAM' => $this->lexicalValues->parameterMarker(),

                default => str_ends_with($name, '_P')
                    ? substr($name, 0, -2)
                    : $name,
            };

            if ($token !== null) {
                $tokens[] = $token;
            }
        }

        return TokenJoiner::join($tokens, [
            ['::', '*'],
            ['*', '::'],
        ]);
    }

    private function nextCanonicalIdentifier(): string
    {
        return $this->rsg->canonicalIdentifier($this->identifierOrdinal++);
    }

    private function generateOperator(): string
    {
        /** @var string $op */
        $op = $this->faker->randomElement(['+', '-', '*', '/', '<', '>', '=', '~', '!', '@', '#', '%', '^', '&', '|']);
        return $op;
    }
}
