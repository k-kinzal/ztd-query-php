<?php

declare(strict_types=1);

namespace Fuzz\Target;

use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\LexemeConstraint;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Generation\Plan\RulePlan;

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
     */
    public function plan(string $mode, string $grammar): GenerationPlan
    {
        $root = in_array($grammar, ['mysql-5.6.51', 'mysql-5.7.44'], true) ? 'statement' : 'simple_statement_or_begin';
        if ($mode === 'statement') {
            $lifecycle = ProductionPattern::anyOf(...array_map(static fn (string $rule): ProductionPattern => ProductionPattern::exactly($rule), ['shutdown_stmt', 'restart_server_stmt', 'clone_stmt', 'kill', 'shutdown', 'kill_type']));

            return GenerationPlan::fromRule($root)->requiringNonEmpty()
                ->withRule($root === 'statement' ? 'statement' : 'simple_statement', RulePlan::any()->allowing(ProductionPattern::excluding($lifecycle)));
        }
        $start = match ($mode) {
            'expression' => 'expr',
            'query', 'select' => 'select_stmt',
            default => 'simple_statement',
        };
        $plan = $this->named(GenerationPlan::fromRule($start)->requiringNonEmpty());
        if ($mode === 'select') {
            $plan = $this->mainstream($plan);
        }
        if ($mode === 'write') {
            $plan = $plan->withRule('simple_statement', RulePlan::any()->allowing(ProductionPattern::anyOf(
                ProductionPattern::exactly('insert_stmt'),
                ProductionPattern::exactly('replace_stmt'),
                ProductionPattern::exactly('update_stmt'),
                ProductionPattern::exactly('delete_stmt'),
            )));
        }

        return $plan->withExpansionBudget((int) (getenv('MYSQL_MEMORY_BUDGET') ?: 96));
    }

    /**
     * Constrains a query to the common forms: SELECT from the fixture tables, joins and derived tables, without INTO, locking, partitions or samples.
     */
    public function mainstream(GenerationPlan $plan): GenerationPlan
    {
        $empty = RulePlan::any()->allowing(ProductionPattern::exactly());

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
            ->withRule('opt_tablesample_clause', $empty)
            ->withRule('opt_window_clause', $empty)
            ->withRule('opt_qualify_clause', $empty)
            ->withRule('table_wild', RulePlan::any()->withLexeme('IDENT', LexemeConstraint::oneOf('t1', 't2', 'x')))
            ->withRule('select_alias', RulePlan::any()->withLexeme('IDENT', LexemeConstraint::oneOf('x', 'y', 'a')))
            ->withRule('opt_table_alias', RulePlan::any()->withLexeme('IDENT', LexemeConstraint::oneOf('x', 'y')));
    }

    /**
     * Constrains table and column names to the fixture.
     */
    public function named(GenerationPlan $plan): GenerationPlan
    {
        $collations = LexemeConstraint::oneOf('utf8mb4_bin', 'utf8mb4_0900_ai_ci', 'utf8mb4_general_ci', 'utf8mb4_0900_as_cs', 'latin1_swedish_ci', 'binary');
        $charsets = LexemeConstraint::oneOf('utf8mb4', 'latin1', 'binary', 'ascii', 'utf8mb3');

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
