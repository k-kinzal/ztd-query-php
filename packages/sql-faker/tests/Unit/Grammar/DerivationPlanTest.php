<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\DerivationPlan;
use SqlFaker\Grammar\ProductionPattern;

#[CoversClass(DerivationPlan::class)]
#[UsesClass(ProductionPattern::class)]
final class DerivationPlanTest extends TestCase
{
    public function testReturnsTheNextPatternForEachRuleOccurrence(): void
    {
        $first = ProductionPattern::containing('CONSTRAINT');
        $second = ProductionPattern::containing('FOREIGN', 'KEY');
        $plan = new DerivationPlan(['constraint' => [$first, $second]]);

        self::assertSame($first, $plan->nextPattern('constraint'));
        self::assertSame($second, $plan->nextPattern('constraint'));
        self::assertNull($plan->nextPattern('constraint'));
        self::assertNull($plan->nextPattern('unknown'));
    }

    public function testRestartReturnsAnUnconsumedPlan(): void
    {
        $pattern = ProductionPattern::containing('CONSTRAINT');
        $plan = new DerivationPlan(['constraint' => [$pattern]]);

        self::assertSame($pattern, $plan->nextPattern('constraint'));
        self::assertNull($plan->nextPattern('constraint'));
        self::assertSame($pattern, $plan->restart()->nextPattern('constraint'));
    }

    public function testUnrestrictedPlanNeverSelectsAPattern(): void
    {
        self::assertNull(DerivationPlan::unrestricted()->nextPattern('constraint'));
    }
}
