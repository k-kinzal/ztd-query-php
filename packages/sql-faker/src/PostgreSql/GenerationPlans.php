<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;

final class GenerationPlans
{
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
