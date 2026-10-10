<?php

declare(strict_types=1);

namespace Fuzz\Target;

use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Generation\Plan\RulePlan;
use SqlFaker\MySql\Grammar\MySqlGrammar;

/**
 * The generation plans of the differential fuzz targets.
 *
 * The statement plan is the whole grammar except the statements that stop, restart, clone or kill
 * the server and its sessions. The other plans name only the fixture tables and
 * columns, so the generated statements read and write rows instead of failing on unknown names.
 */
final class Plans
{
    /**
     * Answers the plan of a mode: statement, query, expression or write.
     *
     * @return GenerationPlan<true>
     */
    public function plan(string $mode, string $grammar): GenerationPlan
    {
        $legacy = in_array($grammar, ['mysql-5.6.51', 'mysql-5.7.44'], true);
        $root = $legacy ? 'statement' : 'simple_statement_or_begin';
        if ($mode === 'statement') {
            $lifecycle = ProductionPattern::anyOf(...array_map(static fn (string $rule): ProductionPattern => ProductionPattern::exactly($rule), ['shutdown_stmt', 'restart_server_stmt', 'clone_stmt', 'kill', 'shutdown', 'kill_type']));

            return GenerationPlan::fromRule($root)->requiringNonEmpty()
                ->withRule($root === 'statement' ? 'statement' : 'simple_statement', RulePlan::any()->allowing(ProductionPattern::excluding($lifecycle)));
        }
        $start = match ($mode) {
            'expression' => 'expr',
            'query', 'select' => $legacy ? 'select' : 'select_stmt',
            default => $legacy ? 'statement' : 'simple_statement',
        };
        $plan = $this->named(GenerationPlan::fromRule($start)->requiringNonEmpty(), $grammar);
        if ($mode === 'select') {
            $plan = $legacy ? $this->legacy($plan, $grammar) : $this->mainstream($plan, $grammar);
        }
        if ($mode === 'write') {
            $writes = $grammar === 'mysql-5.6.51' ? ['insert', 'replace', 'update', 'delete'] : ['insert_stmt', 'replace_stmt', 'update_stmt', 'delete_stmt'];
            $plan = $plan->withRule($legacy ? 'statement' : 'simple_statement', RulePlan::any()->allowing(ProductionPattern::anyOf(...array_map(static fn (string $rule): ProductionPattern => ProductionPattern::exactly($rule), $writes))));
        }

        return $plan->withExpansionBudget($this->budget());
    }

    /**
     * Answers the expansion budget: MYSQL_MEMORY_BUDGET, or 96.
     */
    public function budget(): int
    {
        $budget = getenv('MYSQL_MEMORY_BUDGET');

        return is_string($budget) && $budget !== '' ? (int) $budget : 96;
    }

    /**
     * Constrains a query to the common forms: SELECT from the fixture tables, joins and derived tables, without INTO, locking, partitions or samples.
     *
     * Rules the grammar of the release lacks, such as QUALIFY and TABLESAMPLE before 8.4, are left out.
     *
     * @param GenerationPlan<true> $plan
     * @return GenerationPlan<true>
     */
    public function mainstream(GenerationPlan $plan, string $grammar): GenerationPlan
    {
        $empty = RulePlan::any()->allowing(ProductionPattern::exactly());
        $rules = MySqlGrammar::load($grammar)->ruleMap;
        foreach (['opt_tablesample_clause', 'opt_qualify_clause'] as $rule) {
            if (isset($rules[$rule])) {
                $plan = $plan->withRule($rule, $empty);
            }
        }

        return $plan
            ->withRule('select_stmt', RulePlan::any()->allowing(ProductionPattern::exactly('query_expression')))
            ->withRule('query_primary', RulePlan::any()->allowing(ProductionPattern::exactly('query_specification')))
            ->withRule('query_specification', RulePlan::any()->allowing(ProductionPattern::excluding(ProductionPattern::containing('into_clause'))))
            ->withRule('opt_from_clause', RulePlan::any()->allowing(ProductionPattern::nonEmpty()))
            ->withRule('from_tables', RulePlan::any()->allowing(ProductionPattern::exactly('table_reference_list')))
            ->withRule('table_reference', RulePlan::any()->allowing(ProductionPattern::anyOf(ProductionPattern::exactly('table_factor'), ProductionPattern::exactly('joined_table'))))
            ->withRule('table_factor', RulePlan::any()->allowing(ProductionPattern::anyOf(ProductionPattern::exactly('single_table'), ProductionPattern::exactly('derived_table'))))
            ->withRule('opt_use_partition', $empty)
            ->withRule('opt_index_hints_list', $empty)
            ->withRule('opt_window_clause', $empty)
            ->withRule('table_wild', RulePlan::any()->withLexeme('IDENT', LexemeConstraint::oneOf('t1', 't2', 'x')))
            ->withRule('select_alias', RulePlan::any()->withLexeme('IDENT', LexemeConstraint::oneOf('x', 'y', 'a')))
            ->withRule('opt_table_alias', RulePlan::any()->withLexeme('IDENT', LexemeConstraint::oneOf('x', 'y')));
    }

    /**
     * Constrains a query of the 5.6 and 5.7 grammars to the common forms, as mainstream() does for the later grammars.
     *
     * The 5.x grammars name the select rules differently: a SELECT with a FROM clause, joins and
     * derived tables, without INTO, locking, PROCEDURE ANALYSE, partitions, index hints or ODBC escapes.
     *
     * @param GenerationPlan<true> $plan
     * @return GenerationPlan<true>
     */
    public function legacy(GenerationPlan $plan, string $grammar): GenerationPlan
    {
        $empty = RulePlan::any()->allowing(ProductionPattern::exactly());
        if ($grammar === 'mysql-5.6.51') {
            $plan = $plan
                ->withRule('select_init', RulePlan::any()->allowing(ProductionPattern::exactly('SELECT_SYM', 'select_init2')))
                ->withRule('select_into', RulePlan::any()->allowing(ProductionPattern::exactly('select_from')))
                ->withRule('select_from', RulePlan::any()->allowing(ProductionPattern::containing('join_table_list')))
                ->withRule('procedure_analyse_clause', $empty)
                ->withRule('select_lock_type', $empty);
        } else {
            $plan = $plan
                ->withRule('select_init', RulePlan::any()->allowing(ProductionPattern::exactly('SELECT_SYM', 'select_part2', 'opt_union_clause')))
                ->withRule('select_part2', RulePlan::any()->allowing(ProductionPattern::containing('from_clause')))
                ->withRule('table_reference_list', RulePlan::any()->allowing(ProductionPattern::exactly('join_table_list')))
                ->withRule('opt_into', $empty)
                ->withRule('opt_procedure_analyse_clause', $empty)
                ->withRule('opt_select_lock_type', $empty);
        }

        return $plan
            ->withRule('esc_table_ref', RulePlan::any()->allowing(ProductionPattern::exactly('table_ref')))
            ->withRule('table_factor', RulePlan::any()->allowing(ProductionPattern::anyOf(ProductionPattern::containing('table_ident'), ProductionPattern::containing('select_derived_union'))))
            ->withRule('opt_use_partition', $empty)
            ->withRule('opt_index_hints_list', $empty)
            ->withRule('table_wild', RulePlan::any()->withLexeme('IDENT', LexemeConstraint::oneOf('t1', 't2', 'x')))
            ->withRule('select_alias', RulePlan::any()->withLexeme('IDENT', LexemeConstraint::oneOf('x', 'y', 'a')))
            ->withRule('opt_table_alias', RulePlan::any()->withLexeme('IDENT', LexemeConstraint::oneOf('x', 'y')));
    }

    /**
     * Constrains table and column names to the fixture.
     *
     * The collations offered are those the release has: the 5.x releases lack the utf8mb4_0900 ones.
     * Their COLLATE operator names its collation with ident_or_text instead of collation_name.
     *
     * @param GenerationPlan<true> $plan
     * @return GenerationPlan<true>
     */
    public function named(GenerationPlan $plan, string $grammar = 'mysql-8.4.7'): GenerationPlan
    {
        $collations = in_array($grammar, ['mysql-5.6.51', 'mysql-5.7.44'], true)
            ? LexemeConstraint::oneOf('utf8mb4_bin', 'utf8mb4_unicode_ci', 'utf8mb4_general_ci', 'utf8_general_ci', 'latin1_swedish_ci', 'binary')
            : LexemeConstraint::oneOf('utf8mb4_bin', 'utf8mb4_0900_ai_ci', 'utf8mb4_general_ci', 'utf8mb4_0900_as_cs', 'latin1_swedish_ci', 'binary');
        $charsets = in_array($grammar, ['mysql-5.6.51', 'mysql-5.7.44'], true) ? LexemeConstraint::oneOf('utf8mb4', 'latin1', 'binary', 'ascii', 'utf8') : LexemeConstraint::oneOf('utf8mb4', 'latin1', 'binary', 'ascii', 'utf8mb3');
        if (in_array($grammar, ['mysql-5.6.51', 'mysql-5.7.44'], true)) {
            $plan = $plan->withRule('ident_or_text', RulePlan::any()->allowing(ProductionPattern::exactly('ident'))->withLexeme('IDENT', $collations));
        }

        return $plan
            ->withRule('simple_expr', RulePlan::any()->allowing(ProductionPattern::excluding(ProductionPattern::exactly('param_marker'))))
            ->withRule('collation_name', RulePlan::any()->allowing(ProductionPattern::exactly('ident_or_text'))->withRule('ident_or_text', RulePlan::any()->allowing(ProductionPattern::exactly('ident'))->withLexeme('IDENT', $collations)))
            ->withRule('charset_name', RulePlan::any()->allowing(ProductionPattern::exactly('ident_or_text'))->withRule('ident_or_text', RulePlan::any()->allowing(ProductionPattern::exactly('ident'))->withLexeme('IDENT', $charsets)))
            ->withRule('ident', RulePlan::any()->allowing(ProductionPattern::exactly('IDENT_sys')))
            ->withRule('IDENT_sys', RulePlan::any()->allowing(ProductionPattern::exactly('IDENT')))
            ->withRule('table_ident', RulePlan::any()->allowing(ProductionPattern::exactly('ident'))->withLexeme('IDENT', LexemeConstraint::oneOf(...Fixture::TABLES)))
            ->withRule('simple_ident', RulePlan::any()->allowing(ProductionPattern::exactly('ident'))->withLexeme('IDENT', LexemeConstraint::oneOf(...Fixture::COLUMNS)));
    }

    /**
     * Wraps a generated fragment into the statement a mode runs.
     */
    public function statement(string $mode, string $generated): string
    {
        return $mode === 'expression' ? 'SELECT ' . $generated . ' FROM t1' : $generated;
    }
}
