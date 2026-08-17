<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;

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
