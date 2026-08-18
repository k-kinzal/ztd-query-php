<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;
use SqlFaker\MySql\Grammar\Grammar;

final class GenerationPlans
{
    /** @return GenerationPlan<true> */
    public static function withoutEmptyRows(?string $startRule = null): GenerationPlan
    {
        $plan = $startRule === null
            ? GenerationPlan::all()
            : GenerationPlan::fromRule($startRule);

        return $plan
            ->withPatternForEveryOccurrence('opt_values', ProductionPattern::nonEmpty())
            ->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function foreignKeyConstraint(Grammar $grammar): GenerationPlan
    {
        $startRule = isset($grammar->ruleMap['table_constraint_def'])
            ? 'table_constraint_def'
            : 'key_def';
        $constraintNameRule = isset($grammar->ruleMap['opt_constraint_name'])
            ? 'opt_constraint_name'
            : 'opt_constraint';
        $constraintNameSymbol = $constraintNameRule === 'opt_constraint_name'
            ? 'CONSTRAINT'
            : 'constraint';

        return GenerationPlan::constrained($startRule, [
            $startRule => [ProductionPattern::containing('FOREIGN', 'KEY_SYM')],
            $constraintNameRule => [ProductionPattern::containing($constraintNameSymbol)],
            'opt_ident' => [ProductionPattern::nonEmpty()],
            'opt_ref_list' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function multiTableUpdateStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('update_stmt', [
            'opt_with_clause' => [ProductionPattern::exactly()],
            'table_reference_list' => [
                ProductionPattern::exactly('table_reference_list', ',', 'table_reference'),
                ProductionPattern::exactly('table_reference'),
            ],
            'table_reference' => [
                ProductionPattern::exactly('table_factor'),
                ProductionPattern::exactly('table_factor'),
            ],
            'table_factor' => [
                ProductionPattern::exactly('single_table'),
                ProductionPattern::exactly('single_table'),
            ],
            'opt_use_partition' => [
                ProductionPattern::exactly(),
                ProductionPattern::exactly(),
            ],
            'update_list' => [
                ProductionPattern::exactly('update_list', ',', 'update_elem'),
                ProductionPattern::exactly('update_elem'),
            ],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function updateJoinDerivedStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('update_stmt', [
            'opt_with_clause' => [ProductionPattern::exactly()],
            'table_reference' => [
                ProductionPattern::exactly('joined_table'),
                ProductionPattern::exactly('table_factor'),
                ProductionPattern::exactly('table_factor'),
                ProductionPattern::exactly('table_factor'),
            ],
            'joined_table' => [ProductionPattern::containing('ON_SYM')],
            'table_factor' => [
                ProductionPattern::exactly('single_table'),
                ProductionPattern::exactly('derived_table'),
                ProductionPattern::exactly('single_table'),
            ],
            'opt_from_clause' => [ProductionPattern::nonEmpty()],
            'opt_group_clause' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function insertSelectCompoundStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('insert_stmt', [
            'insert_stmt' => [ProductionPattern::containing('insert_query_expression')],
            'query_expression_body' => [
                ProductionPattern::containing('UNION_SYM'),
                ProductionPattern::exactly('query_primary'),
                ProductionPattern::exactly('query_primary'),
            ],
            'query_primary' => [
                ProductionPattern::exactly('query_specification'),
            ],
            'union_option' => [ProductionPattern::exactly('ALL')],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function insertRowAliasUpsertStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('insert_stmt', [
            'insert_stmt' => [ProductionPattern::containing('insert_from_constructor')],
            'insert_from_constructor' => [ProductionPattern::containing('insert_values')],
            'value_or_values' => [ProductionPattern::exactly('VALUES')],
            'opt_values_reference' => [ProductionPattern::nonEmpty()],
            'opt_insert_update_list' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }

    /** @return GenerationPlan<true> */
    public static function multiTableDeleteStatement(): GenerationPlan
    {
        return GenerationPlan::constrained('delete_stmt', [
            'delete_stmt' => [ProductionPattern::containing('table_alias_ref_list', 'table_reference_list')],
            'opt_with_clause' => [ProductionPattern::exactly()],
            'table_alias_ref_list' => [
                ProductionPattern::exactly('table_alias_ref_list', ',', 'table_ident_opt_wild'),
                ProductionPattern::exactly('table_ident_opt_wild'),
            ],
            'table_reference_list' => [
                ProductionPattern::exactly('table_reference_list', ',', 'table_reference'),
                ProductionPattern::exactly('table_reference'),
            ],
            'table_reference' => [
                ProductionPattern::exactly('table_factor'),
                ProductionPattern::exactly('table_factor'),
            ],
            'table_factor' => [
                ProductionPattern::exactly('single_table'),
                ProductionPattern::exactly('single_table'),
            ],
            'opt_use_partition' => [
                ProductionPattern::exactly(),
                ProductionPattern::exactly(),
            ],
        ])->requiringNonEmpty();
    }
}
