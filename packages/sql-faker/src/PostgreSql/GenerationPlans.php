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
    public static function generatedColumnStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('CreateStmt', [
            'CreateStmt' => [ProductionPattern::containing('OptTableElementList')],
            'OptTableElementList' => [ProductionPattern::nonEmpty()],
            'TableElement' => [ProductionPattern::exactly('columnDef')],
            'ColQualList' => [
                ProductionPattern::exactly('ColQualList', 'ColConstraint'),
                ProductionPattern::exactly(),
            ],
            'ColConstraint' => [ProductionPattern::containing('ColConstraintElem')],
            'ColConstraintElem' => [ProductionPattern::containing('GENERATED', 'STORED')],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function foreignKeyCascadeStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('CreateStmt', [
            'CreateStmt' => [ProductionPattern::containing('OptTableElementList')],
            'OptTableElementList' => [ProductionPattern::nonEmpty()],
            'TableElement' => [ProductionPattern::exactly('TableConstraint')],
            'ConstraintElem' => [ProductionPattern::containing('FOREIGN', 'KEY', 'REFERENCES')],
            'key_actions' => [ProductionPattern::containing('key_update', 'key_delete')],
            'key_action' => [
                ProductionPattern::exactly('CASCADE'),
                ProductionPattern::exactly('CASCADE'),
            ],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function partitionOfStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('CreateStmt', [
            'CreateStmt' => [ProductionPattern::containing('PARTITION', 'OF', 'PartitionBoundSpec')],
            'PartitionBoundSpec' => [ProductionPattern::containing('FROM', 'TO')],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function tableSampleStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('SelectStmt', [
            'SelectStmt' => [ProductionPattern::exactly('select_no_parens')],
            'select_no_parens' => [ProductionPattern::exactly('simple_select')],
            'simple_select' => [
                ProductionPattern::containing('SELECT', 'opt_target_list', 'from_clause'),
            ],
            'from_clause' => [ProductionPattern::nonEmpty()],
            'table_ref' => [ProductionPattern::containing('relation_expr', 'tablesample_clause')],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function doStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('DoStmt', [
            'dostmt_opt_list' => [ProductionPattern::exactly('dostmt_opt_item')],
            'dostmt_opt_item' => [ProductionPattern::exactly('Sconst')],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function mergeStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('MergeStmt', [
            'merge_when_list' => [
                ProductionPattern::exactly('merge_when_list', 'merge_when_clause'),
                ProductionPattern::exactly('merge_when_list', 'merge_when_clause'),
                ProductionPattern::exactly('merge_when_list', 'merge_when_clause'),
                ProductionPattern::exactly('merge_when_clause'),
            ],
            'merge_when_clause' => [
                ProductionPattern::containing('merge_when_tgt_matched', 'merge_delete'),
                ProductionPattern::containing('merge_when_tgt_matched', 'DO', 'NOTHING'),
                ProductionPattern::containing('merge_when_tgt_matched', 'merge_update'),
                ProductionPattern::containing('merge_when_tgt_not_matched', 'merge_insert'),
            ],
        ])->requiringNonEmpty();
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
