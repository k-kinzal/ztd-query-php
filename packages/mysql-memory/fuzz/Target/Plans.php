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
 * The statement plan is the whole grammar. The other plans name only the fixture tables and
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
            return GenerationPlan::fromRule($root)->requiringNonEmpty();
        }
        $start = match ($mode) {
            'expression' => 'expr',
            'query' => 'select_stmt',
            default => 'simple_statement',
        };
        $plan = $this->named(GenerationPlan::fromRule($start)->requiringNonEmpty());
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
