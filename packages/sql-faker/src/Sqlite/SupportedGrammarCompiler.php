<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Contract\Grammar as ContractGrammar;
use SqlFaker\Contract\RewriteProgram;
use SqlFaker\Contract\RewriteStep;
use SqlFaker\Grammar\ContractGrammarHydrator;
use SqlFaker\Grammar\ContractGrammarProjector;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;

final class SupportedGrammarCompiler
{
    private const SELECT_ARITY_LIMIT = 8;

    public function compile(ContractGrammar $snapshot): ContractGrammar
    {
        return ContractGrammarProjector::project(
            $this->compileSourceGrammar(ContractGrammarHydrator::hydrate($snapshot)),
            NonTerminal::class,
        );
    }

    public function rewriteProgram(): RewriteProgram
    {
        return new RewriteProgram([
            new RewriteStep('extract.statement_entry_rules', 'Extract statement-specific entry rules from cmd.'),
            new RewriteStep('filter.delete_order_by_forms', 'Remove unsupported DELETE ORDER BY forms.'),
            new RewriteStep('filter.unsafe_expression_branches', 'Remove unsafe expression branches.'),
            new RewriteStep('filter.window_branches', 'Remove underconstrained window branches.'),
            new RewriteStep('filter.keyword_like_identifier_branches', 'Remove keyword-like identifier branches.'),
            new RewriteStep('rebuild.create_table', 'Rebuild CREATE TABLE around safe wrapper rules.'),
            new RewriteStep('rebuild.attach_detach_vacuum', 'Rebuild ATTACH, DETACH, and VACUUM around safe wrappers.'),
            new RewriteStep('rebuild.temporary_object_families', 'Rebuild temporary-object families around explicit wrappers.'),
            new RewriteStep('rebuild.bounded_select_families', 'Rebuild SELECT around bounded safe families.'),
            new RewriteStep('publish.extracted_statement_rules', 'Publish the extracted statement rules as named entry points.'),
        ]);
    }

    public function compileSourceGrammar(Grammar $grammar): Grammar
    {
        $ruleMap = $grammar->ruleMap;
        $cmd = $ruleMap['cmd'] ?? null;
        if ($cmd === null) {
            return $grammar;
        }

        foreach ($this->rewriteProgram()->steps as $step) {
            $ruleMap = $this->applyRewriteStep($step->id, $ruleMap, $cmd);
        }

        return new Grammar($grammar->startSymbol, $ruleMap);
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function applyRewriteStep(string $stepId, array $ruleMap, ProductionRule $cmd): array
    {
        return match ($stepId) {
            'extract.statement_entry_rules' => $this->extractStatementGroups($ruleMap, $cmd),
            'filter.delete_order_by_forms' => $this->filterDeleteOrderByForms($ruleMap),
            'filter.unsafe_expression_branches' => $this->filterExprRule($ruleMap),
            'filter.window_branches' => $this->filterWindowRule($ruleMap),
            'filter.keyword_like_identifier_branches' => $this->filterKeywordLikeIdentifierBranches($ruleMap),
            'rebuild.create_table' => $this->augmentCreateTableRule($ruleMap),
            'rebuild.attach_detach_vacuum' => $this->rebuildAttachDetachVacuumFamilies($ruleMap),
            'rebuild.temporary_object_families' => $this->augmentTemporaryObjectRule($ruleMap),
            'rebuild.bounded_select_families' => $this->augmentSelectRule($ruleMap),
            'publish.extracted_statement_rules' => $this->publishStatementRules($ruleMap),
            default => throw new \LogicException(sprintf('Unknown SQLite rewrite step: %s', $stepId)),
        };
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function extractStatementGroups(array $ruleMap, ProductionRule $cmd): array
    {
        $groups = $this->classifyCmdAlternatives($cmd);

        foreach ($groups as $key => $alternatives) {
            $ruleMap['__extracted_' . $key] = new ProductionRule('__extracted_' . $key, $alternatives);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterDeleteOrderByForms(array $ruleMap): array
    {
        $rule = $ruleMap['__extracted_delete'] ?? null;
        if ($rule === null) {
            return $ruleMap;
        }

        $ruleMap['__extracted_delete'] = new ProductionRule('__extracted_delete', array_values(array_filter(
            $rule->alternatives,
            fn (Production $alt): bool => !$this->hasNonTerminal($alt, 'orderby_opt'),
        )));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterKeywordLikeIdentifierBranches(array $ruleMap): array
    {
        $ruleMap = $this->filterNmRule($ruleMap);

        return $this->filterNmnumRule($ruleMap);
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function rebuildAttachDetachVacuumFamilies(array $ruleMap): array
    {
        $ruleMap = $this->augmentAttachRule($ruleMap);
        $ruleMap = $this->augmentVacuumRule($ruleMap);

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function publishStatementRules(array $ruleMap): array
    {
        $ruleNames = [
            'insert' => '__extracted_insert',
            'delete' => '__extracted_delete',
            'update' => '__extracted_update',
            'drop_table' => '__extracted_drop_table',
            'alter_table' => '__extracted_alter_table',
        ];

        foreach ($ruleNames as $ruleName => $extractedRuleName) {
            $rule = $ruleMap[$extractedRuleName] ?? null;
            unset($ruleMap[$extractedRuleName]);

            if ($rule === null || $rule->alternatives === []) {
                continue;
            }

            $ruleMap[$ruleName] = new ProductionRule($ruleName, $rule->alternatives);
        }

        return $ruleMap;
    }

    /**
     * @return array<string, list<Production>>
     */
    private function classifyCmdAlternatives(ProductionRule $cmd): array
    {
        $groups = [
            'insert' => [],
            'delete' => [],
            'update' => [],
            'drop_table' => [],
            'alter_table' => [],
        ];

        foreach ($cmd->alternatives as $alt) {
            if ($alt->symbols === []) {
                continue;
            }

            $first = $alt->symbols[0];

            if ($first instanceof Terminal) {
                match ($first->value) {
                    'DELETE' => $groups['delete'][] = $alt,
                    'UPDATE' => $groups['update'][] = $alt,
                    'ALTER' => $this->secondTerminalIs($alt, 'TABLE') ? $groups['alter_table'][] = $alt : null,
                    'DROP' => $this->secondTerminalIs($alt, 'TABLE') ? $groups['drop_table'][] = $alt : null,
                    default => null,
                };
            } elseif ($first instanceof NonTerminal) {
                if (count($alt->symbols) < 2) {
                    continue;
                }

                $second = $alt->symbols[1];
                if ($second instanceof Terminal) {
                    match ($second->value) {
                        'DELETE' => $groups['delete'][] = $alt,
                        'UPDATE' => $groups['update'][] = $alt,
                        default => null,
                    };
                } elseif ($second instanceof NonTerminal && $second->value === 'insert_cmd') {
                    $groups['insert'][] = $alt;
                }
            }
        }

        return $groups;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterExprRule(array $ruleMap): array
    {
        $expr = $ruleMap['expr'] ?? null;
        if ($expr === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $expr->alternatives,
            static fn (Production $alt): bool => !self::isWithinGroupAlternative($alt) && !self::isRaiseAlternative($alt),
        ));
        if ($filtered !== []) {
            $ruleMap['expr'] = new ProductionRule('expr', $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterWindowRule(array $ruleMap): array
    {
        $window = $ruleMap['window'] ?? null;
        if ($window === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $window->alternatives,
            static fn (Production $alt): bool => !self::isFrameOptOnlyAlternative($alt),
        ));
        if ($filtered !== []) {
            $ruleMap['window'] = new ProductionRule('window', $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterNmnumRule(array $ruleMap): array
    {
        $nmnum = $ruleMap['nmnum'] ?? null;
        if ($nmnum === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $nmnum->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;

                return !$first instanceof Terminal
                    || !in_array($first->value, ['ON', 'DELETE', 'DEFAULT'], true);
            },
        ));

        if ($filtered !== []) {
            $ruleMap['nmnum'] = new ProductionRule('nmnum', $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function filterNmRule(array $ruleMap): array
    {
        $nm = $ruleMap['nm'] ?? null;
        if ($nm === null) {
            return $ruleMap;
        }

        $filtered = array_values(array_filter(
            $nm->alternatives,
            static function (Production $alt): bool {
                $first = $alt->symbols[0] ?? null;

                return !$first instanceof Terminal || $first->value !== 'STRING';
            },
        ));

        if ($filtered !== []) {
            $ruleMap['nm'] = new ProductionRule('nm', $filtered);
        }

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentCreateTableRule(array $ruleMap): array
    {
        if (!isset($ruleMap['cmd'], $ruleMap['create_table'], $ruleMap['create_table_args'])) {
            return $ruleMap;
        }

        $ruleMap['safe_dbnm'] = new ProductionRule('safe_dbnm', [
            new Production([]),
            new Production([new Terminal('DOT'), new NonTerminal('nm')]),
        ]);
        $ruleMap['create_table_head'] = new ProductionRule('create_table_head', [
            new Production([
                new NonTerminal('createkw'),
                new Terminal('TABLE'),
                new NonTerminal('ifnotexists'),
                new NonTerminal('nm'),
                new NonTerminal('safe_dbnm'),
            ]),
            new Production([
                new NonTerminal('createkw'),
                new Terminal('TEMP'),
                new Terminal('TABLE'),
                new NonTerminal('ifnotexists'),
                new NonTerminal('nm'),
            ]),
        ]);
        $ruleMap['create_table'] = new ProductionRule('create_table', [
            new Production([
                new NonTerminal('create_table_head'),
                new NonTerminal('create_table_args'),
            ]),
        ]);
        $ruleMap['cmd'] = new ProductionRule('cmd', array_map(
            static function (Production $alt): Production {
                $names = array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                );

                if ($names === ['create_table', 'create_table_args']) {
                    return new Production([new NonTerminal('create_table')]);
                }

                return $alt;
            },
            $ruleMap['cmd']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentAttachRule(array $ruleMap): array
    {
        if (!isset($ruleMap['cmd'])) {
            return $ruleMap;
        }

        $ruleMap['safe_attach_filename_expr'] = new ProductionRule('safe_attach_filename_expr', [
            new Production([new Terminal('STRING')]),
        ]);
        $ruleMap['safe_attach_schema_expr'] = new ProductionRule('safe_attach_schema_expr', [
            new Production([new NonTerminal('nm')]),
        ]);
        $ruleMap['attach_stmt'] = new ProductionRule('attach_stmt', [
            new Production([
                new Terminal('ATTACH'),
                new NonTerminal('database_kw_opt'),
                new NonTerminal('safe_attach_filename_expr'),
                new Terminal('AS'),
                new NonTerminal('safe_attach_schema_expr'),
                new NonTerminal('key_opt'),
            ]),
        ]);
        $ruleMap['detach_stmt'] = new ProductionRule('detach_stmt', [
            new Production([
                new Terminal('DETACH'),
                new NonTerminal('database_kw_opt'),
                new NonTerminal('safe_attach_schema_expr'),
            ]),
        ]);
        $ruleMap['cmd'] = new ProductionRule('cmd', array_map(
            static function (Production $alt): Production {
                $names = array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                );

                if ($names === ['ATTACH', 'database_kw_opt', 'expr', 'AS', 'expr', 'key_opt']) {
                    return new Production([new NonTerminal('attach_stmt')]);
                }

                if ($names === ['DETACH', 'database_kw_opt', 'expr']) {
                    return new Production([new NonTerminal('detach_stmt')]);
                }

                return $alt;
            },
            $ruleMap['cmd']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentVacuumRule(array $ruleMap): array
    {
        if (!isset($ruleMap['cmd'])) {
            return $ruleMap;
        }

        $ruleMap['safe_vacuum_into_expr'] = new ProductionRule('safe_vacuum_into_expr', [
            new Production([new Terminal('STRING')]),
        ]);
        $ruleMap['safe_vinto'] = new ProductionRule('safe_vinto', [
            new Production([]),
            new Production([
                new Terminal('INTO'),
                new NonTerminal('safe_vacuum_into_expr'),
            ]),
        ]);
        $ruleMap['vacuum_stmt'] = new ProductionRule('vacuum_stmt', [
            new Production([
                new Terminal('VACUUM'),
                new NonTerminal('safe_vinto'),
            ]),
            new Production([
                new Terminal('VACUUM'),
                new NonTerminal('nm'),
                new NonTerminal('safe_vinto'),
            ]),
        ]);
        $ruleMap['cmd'] = new ProductionRule('cmd', array_map(
            static function (Production $alt): Production {
                $names = array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                );

                if ($names === ['VACUUM', 'vinto'] || $names === ['VACUUM', 'nm', 'vinto']) {
                    return new Production([new NonTerminal('vacuum_stmt')]);
                }

                return $alt;
            },
            $ruleMap['cmd']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentTemporaryObjectRule(array $ruleMap): array
    {
        if (!isset($ruleMap['cmd'], $ruleMap['safe_dbnm'], $ruleMap['trigger_decl'])) {
            return $ruleMap;
        }

        $ruleMap['create_view_stmt'] = new ProductionRule('create_view_stmt', [
            new Production([
                new NonTerminal('createkw'),
                new Terminal('VIEW'),
                new NonTerminal('ifnotexists'),
                new NonTerminal('nm'),
                new NonTerminal('safe_dbnm'),
                new NonTerminal('eidlist_opt'),
                new Terminal('AS'),
                new NonTerminal('select'),
            ]),
            new Production([
                new NonTerminal('createkw'),
                new Terminal('TEMP'),
                new Terminal('VIEW'),
                new NonTerminal('ifnotexists'),
                new NonTerminal('nm'),
                new NonTerminal('eidlist_opt'),
                new Terminal('AS'),
                new NonTerminal('select'),
            ]),
        ]);
        $ruleMap['trigger_decl'] = new ProductionRule('trigger_decl', [
            new Production([
                new Terminal('TRIGGER'),
                new NonTerminal('ifnotexists'),
                new NonTerminal('nm'),
                new NonTerminal('safe_dbnm'),
                new NonTerminal('trigger_time'),
                new NonTerminal('trigger_event'),
                new Terminal('ON'),
                new NonTerminal('fullname'),
                new NonTerminal('foreach_clause'),
                new NonTerminal('when_clause'),
            ]),
            new Production([
                new Terminal('TEMP'),
                new Terminal('TRIGGER'),
                new NonTerminal('ifnotexists'),
                new NonTerminal('nm'),
                new NonTerminal('trigger_time'),
                new NonTerminal('trigger_event'),
                new Terminal('ON'),
                new NonTerminal('fullname'),
                new NonTerminal('foreach_clause'),
                new NonTerminal('when_clause'),
            ]),
        ]);
        $ruleMap['create_trigger_stmt'] = new ProductionRule('create_trigger_stmt', [
            new Production([
                new NonTerminal('createkw'),
                new NonTerminal('trigger_decl'),
                new Terminal('BEGIN'),
                new NonTerminal('trigger_cmd_list'),
                new Terminal('END'),
            ]),
        ]);
        $ruleMap['cmd'] = new ProductionRule('cmd', array_map(
            static function (Production $alt): Production {
                $names = array_map(
                    static fn (Symbol $symbol): string => $symbol->value(),
                    $alt->symbols,
                );

                if ($names === ['createkw', 'temp', 'VIEW', 'ifnotexists', 'nm', 'dbnm', 'eidlist_opt', 'AS', 'select']) {
                    return new Production([new NonTerminal('create_view_stmt')]);
                }

                if ($names === ['createkw', 'trigger_decl', 'BEGIN', 'trigger_cmd_list', 'END']) {
                    return new Production([new NonTerminal('create_trigger_stmt')]);
                }

                return $alt;
            },
            $ruleMap['cmd']->alternatives,
        ));

        return $ruleMap;
    }

    /**
     * @param array<string, ProductionRule> $ruleMap
     * @return array<string, ProductionRule>
     */
    private function augmentSelectRule(array $ruleMap): array
    {
        if (!isset($ruleMap['selectnowith'], $ruleMap['oneselect'], $ruleMap['selcollist'], $ruleMap['multiselect_op'])) {
            return $ruleMap;
        }

        $safeNoFromAlternatives = array_values(array_filter(
            $ruleMap['selcollist']->alternatives,
            static function (Production $alt): bool {
                foreach ($alt->symbols as $symbol) {
                    if ($symbol instanceof Terminal && $symbol->value === 'STAR') {
                        return false;
                    }
                }

                return true;
            },
        ));
        if ($safeNoFromAlternatives === []) {
            return $ruleMap;
        }

        $ruleMap['safe_selcollist_no_from'] = new ProductionRule('safe_selcollist_no_from', $safeNoFromAlternatives);
        $ruleMap['safe_from_clause'] = new ProductionRule('safe_from_clause', [
            new Production([new Terminal('FROM'), new NonTerminal('seltablist')]),
        ]);
        $ruleMap['safe_select_result_expr'] = new ProductionRule('safe_select_result_expr', [
            new Production([new NonTerminal('expr')]),
        ]);
        $ruleMap['safe_select_value_expr'] = new ProductionRule('safe_select_value_expr', [
            new Production([new NonTerminal('term')]),
        ]);
        $ruleMap['select_values_clause'] = new ProductionRule('select_values_clause', []);
        $ruleMap['setop_select_stmt'] = new ProductionRule('setop_select_stmt', []);

        foreach (range(1, self::SELECT_ARITY_LIMIT) as $arity) {
            $resultListRule = sprintf('select_result_list_%d', $arity);
            $valuesRowRule = sprintf('select_value_row_%d', $arity);
            $valuesRowListRule = sprintf('select_value_row_list_%d', $arity);
            $valuesClauseRule = sprintf('select_values_clause_%d', $arity);
            $operandRule = sprintf('setop_select_operand_%d', $arity);
            $stmtRule = sprintf('setop_select_stmt_%d', $arity);

            $ruleMap[$resultListRule] = new ProductionRule($resultListRule, [
                new Production($this->buildCommaSeparatedRuleList('safe_select_result_expr', $arity)),
            ]);
            $valueExprListRule = sprintf('select_value_expr_list_%d', $arity);
            $ruleMap[$valueExprListRule] = new ProductionRule($valueExprListRule, [
                new Production($this->buildCommaSeparatedRuleList('safe_select_value_expr', $arity)),
            ]);
            $ruleMap[$valuesRowRule] = new ProductionRule($valuesRowRule, [
                new Production([
                    new Terminal('LP'),
                    new NonTerminal($valueExprListRule),
                    new Terminal('RP'),
                ]),
            ]);
            $ruleMap[$valuesRowListRule] = new ProductionRule($valuesRowListRule, [
                new Production([new NonTerminal($valuesRowRule)]),
                new Production([
                    new NonTerminal($valuesRowListRule),
                    new Terminal('COMMA'),
                    new NonTerminal($valuesRowRule),
                ]),
            ]);
            $ruleMap[$valuesClauseRule] = new ProductionRule($valuesClauseRule, [
                new Production([
                    new Terminal('VALUES'),
                    new NonTerminal($valuesRowListRule),
                ]),
            ]);
            $ruleMap['select_values_clause'] = new ProductionRule('select_values_clause', [
                ...$ruleMap['select_values_clause']->alternatives,
                new Production([new NonTerminal($valuesClauseRule)]),
            ]);
            $ruleMap[$operandRule] = new ProductionRule($operandRule, [
                new Production([
                    new Terminal('SELECT'),
                    new NonTerminal('distinct'),
                    new NonTerminal($resultListRule),
                    new NonTerminal('from'),
                    new NonTerminal('where_opt'),
                    new NonTerminal('groupby_opt'),
                    new NonTerminal('having_opt'),
                    new NonTerminal('orderby_opt'),
                    new NonTerminal('limit_opt'),
                ]),
                new Production([
                    new Terminal('SELECT'),
                    new NonTerminal('distinct'),
                    new NonTerminal($resultListRule),
                    new NonTerminal('from'),
                    new NonTerminal('where_opt'),
                    new NonTerminal('groupby_opt'),
                    new NonTerminal('having_opt'),
                    new NonTerminal('window_clause'),
                    new NonTerminal('orderby_opt'),
                    new NonTerminal('limit_opt'),
                ]),
                new Production([new NonTerminal($valuesClauseRule)]),
            ]);
            $ruleMap[$stmtRule] = new ProductionRule($stmtRule, [
                new Production([
                    new NonTerminal($operandRule),
                    new NonTerminal('multiselect_op'),
                    new NonTerminal($operandRule),
                ]),
                new Production([
                    new NonTerminal($stmtRule),
                    new NonTerminal('multiselect_op'),
                    new NonTerminal($operandRule),
                ]),
            ]);
            $ruleMap['setop_select_stmt'] = new ProductionRule('setop_select_stmt', [
                ...$ruleMap['setop_select_stmt']->alternatives,
                new Production([new NonTerminal($stmtRule)]),
            ]);
        }

        $ruleMap['oneselect'] = new ProductionRule('oneselect', [
            new Production([
                new Terminal('SELECT'),
                new NonTerminal('distinct'),
                new NonTerminal('safe_selcollist_no_from'),
                new NonTerminal('where_opt'),
                new NonTerminal('groupby_opt'),
                new NonTerminal('having_opt'),
                new NonTerminal('orderby_opt'),
                new NonTerminal('limit_opt'),
            ]),
            new Production([
                new Terminal('SELECT'),
                new NonTerminal('distinct'),
                new NonTerminal('safe_selcollist_no_from'),
                new NonTerminal('where_opt'),
                new NonTerminal('groupby_opt'),
                new NonTerminal('having_opt'),
                new NonTerminal('window_clause'),
                new NonTerminal('orderby_opt'),
                new NonTerminal('limit_opt'),
            ]),
            new Production([
                new Terminal('SELECT'),
                new NonTerminal('distinct'),
                new NonTerminal('selcollist'),
                new NonTerminal('safe_from_clause'),
                new NonTerminal('where_opt'),
                new NonTerminal('groupby_opt'),
                new NonTerminal('having_opt'),
                new NonTerminal('orderby_opt'),
                new NonTerminal('limit_opt'),
            ]),
            new Production([
                new Terminal('SELECT'),
                new NonTerminal('distinct'),
                new NonTerminal('selcollist'),
                new NonTerminal('safe_from_clause'),
                new NonTerminal('where_opt'),
                new NonTerminal('groupby_opt'),
                new NonTerminal('having_opt'),
                new NonTerminal('window_clause'),
                new NonTerminal('orderby_opt'),
                new NonTerminal('limit_opt'),
            ]),
            new Production([new NonTerminal('select_values_clause')]),
        ]);
        $ruleMap['selectnowith'] = new ProductionRule('selectnowith', [
            new Production([new NonTerminal('oneselect')]),
            new Production([new NonTerminal('setop_select_stmt')]),
        ]);

        return $ruleMap;
    }

    private static function isFrameOptOnlyAlternative(Production $alt): bool
    {
        $nonTerminals = [];
        $hasTerminal = false;
        foreach ($alt->symbols as $sym) {
            if ($sym instanceof NonTerminal) {
                $nonTerminals[] = $sym->value;
            } elseif ($sym instanceof Terminal) {
                $hasTerminal = true;
            }
        }

        return !$hasTerminal && in_array('frame_opt', $nonTerminals, true)
            && array_diff($nonTerminals, ['nm', 'frame_opt']) === [];
    }

    private static function isWithinGroupAlternative(Production $alt): bool
    {
        foreach ($alt->symbols as $sym) {
            if ($sym instanceof Terminal && $sym->value === 'WITHIN') {
                return true;
            }
        }

        return false;
    }

    private static function isRaiseAlternative(Production $alt): bool
    {
        $first = $alt->symbols[0] ?? null;

        return $first instanceof Terminal && $first->value === 'RAISE';
    }

    private function secondTerminalIs(Production $alt, string $value): bool
    {
        $s1 = $alt->symbols[1] ?? null;

        return $s1 instanceof Terminal && $s1->value === $value;
    }

    private function hasNonTerminal(Production $alt, string $name): bool
    {
        foreach ($alt->symbols as $sym) {
            if ($sym instanceof NonTerminal && $sym->value === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<Symbol>
     */
    private function buildCommaSeparatedRuleList(string $symbol, int $arity): array
    {
        $symbols = [];

        foreach (range(1, $arity) as $position) {
            if ($position > 1) {
                $symbols[] = new Terminal('COMMA');
            }

            $symbols[] = new NonTerminal($symbol);
        }

        return $symbols;
    }

}
