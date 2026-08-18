<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;

final class GenerationPlans
{
    /** @return GenerationPlan<true> */
    public static function insertFunctionUpsertStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('InsertStmt', [
            'opt_with_clause' => [ProductionPattern::exactly()],
            'insert_target' => [ProductionPattern::exactly('qualified_name')],
            'qualified_name' => [ProductionPattern::exactly('ColId')],
            'insert_rest' => [ProductionPattern::containing('insert_column_list', 'SelectStmt')],
            'insert_column_list' => [ProductionPattern::exactly('insert_column_item')],
            'select_no_parens' => [ProductionPattern::exactly('simple_select')],
            'simple_select' => [ProductionPattern::exactly('values_clause')],
            'values_clause' => [ProductionPattern::exactly('VALUES', '(', 'expr_list', ')')],
            'expr_list' => [ProductionPattern::exactly('a_expr')],
            'opt_on_conflict' => [ProductionPattern::containing('DO', 'UPDATE')],
            'opt_conf_expr' => [ProductionPattern::exactly()],
            'opt_indirection' => [
                ProductionPattern::exactly(),
                ProductionPattern::exactly(),
            ],
            'a_expr' => [
                ProductionPattern::exactly('c_expr'),
                ProductionPattern::exactly('c_expr'),
            ],
            'c_expr' => [
                ProductionPattern::exactly('AexprConst'),
                ProductionPattern::exactly('func_expr'),
            ],
            'AexprConst' => [ProductionPattern::exactly('Iconst')],
            'func_expr' => [ProductionPattern::containing('func_application')],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function temporaryTableStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('CreateStmt', [
            'OptTemp' => [ProductionPattern::containing('TEMP')],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function viewStatement(): GenerationPlan
    {
        return GenerationPlan::fromRule('ViewStmt')->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function foreignKeyConstraint(): GenerationPlan
    {
        return GenerationPlan::constrained('TableConstraint', [
            'TableConstraint' => [ProductionPattern::containing('CONSTRAINT')],
            'ConstraintElem' => [ProductionPattern::containing('FOREIGN', 'KEY')],
            'opt_column_list' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }
}
