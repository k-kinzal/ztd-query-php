<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;

final class GenerationPlans
{
    /**
     * @return GenerationPlan<true>
     */
    public static function quotedIdentifier(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('quoted_identifier', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function stringLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('string_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function integerLiteral(int $min, int $max): GenerationPlan
    {
        return GenerationPlan::lexical('integer_literal', compact('min', 'max'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function decimalLiteral(int $precision, int $scale): GenerationPlan
    {
        return GenerationPlan::lexical('decimal_literal', compact('precision', 'scale'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function floatLiteral(
        int $precision,
        int $scale,
        int $minExponent,
        int $maxExponent,
    ): GenerationPlan {
        return GenerationPlan::lexical(
            'float_literal',
            compact('precision', 'scale', 'minExponent', 'maxExponent'),
        );
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function hexLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('hex_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function binaryLiteral(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('binary_literal', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function dollarQuotedString(int $minLength, int $maxLength): GenerationPlan
    {
        return GenerationPlan::lexical('dollar_quoted_string', compact('minLength', 'maxLength'));
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function parameterMarker(int $min, int $max): GenerationPlan
    {
        return GenerationPlan::lexical('parameter_marker', compact('min', 'max'));
    }

    /**
     * @return GenerationPlan<true>
     */
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

    /**
     * @return GenerationPlan<true>
     */
    public static function temporaryTableStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('CreateStmt', [
            'OptTemp' => [ProductionPattern::containing('TEMP')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function viewStatement(): GenerationPlan
    {
        return GenerationPlan::fromRule('ViewStmt')->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
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

    /**
     * @return GenerationPlan<true>
     */
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

    /**
     * @return GenerationPlan<true>
     */
    public static function partitionOfStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('CreateStmt', [
            'CreateStmt' => [ProductionPattern::containing('PARTITION', 'OF', 'PartitionBoundSpec')],
            'PartitionBoundSpec' => [ProductionPattern::containing('FROM', 'TO')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
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

    /**
     * @return GenerationPlan<true>
     */
    public static function doStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('DoStmt', [
            'dostmt_opt_list' => [ProductionPattern::exactly('dostmt_opt_item')],
            'dostmt_opt_item' => [ProductionPattern::exactly('Sconst')],
        ])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
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

    /**
     * @return GenerationPlan<true>
     */
    public static function copyStatement(): GenerationPlan
    {
        return GenerationPlan::fromRule('CopyStmt')->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function partialIndexUpsertStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('InsertStmt', [
            'opt_with_clause' => [ProductionPattern::exactly()],
            'qualified_name' => [ProductionPattern::exactly('ColId')],
            'insert_rest' => [ProductionPattern::exactly('DEFAULT', 'VALUES')],
            'opt_on_conflict' => [ProductionPattern::containing('DO', 'UPDATE')],
            'opt_conf_expr' => [ProductionPattern::containing('index_params', 'where_clause')],
            'where_clause' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }

    /**
     * @return non-empty-list<GenerationPlan<true>>
     */
    public static function domainDmlStatements(): array
    {
        return array_map(
            static fn (string $startRule): GenerationPlan => GenerationPlan::constrained($startRule, [
                'opt_with_clause' => [ProductionPattern::exactly()],
            ])->requiringNonEmpty(),
            ['InsertStmt', 'UpdateStmt', 'DeleteStmt'],
        );
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function fullTextSearchStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('SelectStmt', [
            'SelectStmt' => [ProductionPattern::exactly('select_no_parens')],
            'select_no_parens' => [ProductionPattern::exactly('simple_select')],
            'simple_select' => [
                ProductionPattern::containing('SELECT', 'opt_target_list', 'from_clause', 'where_clause'),
            ],
            'opt_target_list' => [ProductionPattern::nonEmpty()],
            'target_list' => [ProductionPattern::exactly('target_el')],
            'target_el' => [ProductionPattern::exactly('*')],
            'into_clause' => [ProductionPattern::exactly()],
            'from_clause' => [ProductionPattern::nonEmpty()],
            'from_list' => [ProductionPattern::exactly('table_ref')],
            'table_ref' => [ProductionPattern::exactly('relation_expr', 'opt_alias_clause')],
            'relation_expr' => [ProductionPattern::exactly('qualified_name')],
            'qualified_name' => [ProductionPattern::exactly('ColId')],
            'opt_alias_clause' => [ProductionPattern::exactly()],
            'where_clause' => [ProductionPattern::nonEmpty()],
            'group_clause' => [ProductionPattern::exactly()],
            'having_clause' => [ProductionPattern::exactly()],
            'window_clause' => [ProductionPattern::exactly()],
            'a_expr' => [
                ProductionPattern::exactly('a_expr', 'qual_Op', 'a_expr'),
                ProductionPattern::exactly('c_expr'),
                ProductionPattern::exactly('c_expr'),
            ],
            'qual_Op' => [ProductionPattern::exactly('Op')],
            'c_expr' => [
                ProductionPattern::exactly('columnref'),
                ProductionPattern::exactly('columnref'),
            ],
            'columnref' => [
                ProductionPattern::exactly('ColId'),
                ProductionPattern::exactly('ColId'),
            ],
            'ColId' => [
                ProductionPattern::exactly('IDENT'),
                ProductionPattern::exactly('IDENT'),
                ProductionPattern::exactly('IDENT'),
            ],
        ])->withLexemes(['Op' => ['@@']])->requiringNonEmpty();
    }

    /**
     * @return GenerationPlan<true>
     */
    public static function foreignKeyConstraint(): GenerationPlan
    {
        return GenerationPlan::constrained('TableConstraint', [
            'TableConstraint' => [ProductionPattern::containing('CONSTRAINT')],
            'ConstraintElem' => [ProductionPattern::containing('FOREIGN', 'KEY')],
            'opt_column_list' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }
}
