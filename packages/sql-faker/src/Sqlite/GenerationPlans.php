<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\ProductionPattern;

final class GenerationPlans
{
    /** @return GenerationPlan<true> */
    public static function foreignKeyConstraint(): GenerationPlan
    {
        return GenerationPlan::constrained('conslist', [
            'conslist' => [
                ProductionPattern::exactly('conslist', 'tconscomma', 'tcons'),
                ProductionPattern::exactly('tcons'),
            ],
            'tcons' => [
                ProductionPattern::containing('CONSTRAINT'),
                ProductionPattern::containing('FOREIGN', 'KEY'),
            ],
            'tconscomma' => [ProductionPattern::exactly()],
            'eidlist_opt' => [ProductionPattern::nonEmpty()],
        ])->requiringNonEmpty();
    }
}
