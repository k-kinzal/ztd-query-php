<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[UsesClass(RewritePlan::class)]
#[CoversClass(MultiRewritePlan::class)]
final class MultiRewritePlanTest extends TestCase
{
    public function testPlansReturnsAllPlans(): void
    {
        $plan1 = new RewritePlan('SELECT * FROM users', QueryKind::READ);
        $plan2 = new RewritePlan('INSERT INTO users (id) VALUES (1)', QueryKind::WRITE_SIMULATED);
        $plans = [$plan1, $plan2];

        $multiPlan = new MultiRewritePlan($plans);

        self::assertSame($plans, $multiPlan->plans());
    }

    public function testCountReturnsNumberOfPlans(): void
    {
        $plans = [
            new RewritePlan('SELECT 1', QueryKind::READ),
            new RewritePlan('SELECT 2', QueryKind::READ),
            new RewritePlan('SELECT 3', QueryKind::READ),
        ];

        $multiPlan = new MultiRewritePlan($plans);

        self::assertSame(3, $multiPlan->count());
    }

    public function testCountReturnsZeroForEmptyPlans(): void
    {
        $multiPlan = new MultiRewritePlan([]);

        self::assertSame(0, $multiPlan->count());
    }

    public function testFirstReturnsFirstPlan(): void
    {
        $plan1 = new RewritePlan('SELECT 1', QueryKind::READ);
        $plan2 = new RewritePlan('SELECT 2', QueryKind::READ);

        $multiPlan = new MultiRewritePlan([$plan1, $plan2]);

        self::assertSame($plan1, $multiPlan->first());
    }

    public function testFirstReturnsNullForEmptyPlans(): void
    {
        $multiPlan = new MultiRewritePlan([]);

        self::assertNull($multiPlan->first());
    }

    public function testGetReturnsPlanAtIndex(): void
    {
        $plan1 = new RewritePlan('SELECT 1', QueryKind::READ);
        $plan2 = new RewritePlan('SELECT 2', QueryKind::READ);
        $plan3 = new RewritePlan('SELECT 3', QueryKind::READ);

        $multiPlan = new MultiRewritePlan([$plan1, $plan2, $plan3]);

        self::assertSame($plan1, $multiPlan->get(0));
        self::assertSame($plan2, $multiPlan->get(1));
        self::assertSame($plan3, $multiPlan->get(2));
    }

    public function testGetReturnsNullForInvalidIndex(): void
    {
        $multiPlan = new MultiRewritePlan([
            new RewritePlan('SELECT 1', QueryKind::READ),
        ]);

        self::assertNull($multiPlan->get(1));
        self::assertNull($multiPlan->get(-1));
        self::assertNull($multiPlan->get(100));
    }

    public function testGetReturnsNullForEmptyPlans(): void
    {
        $multiPlan = new MultiRewritePlan([]);

        self::assertNull($multiPlan->get(0));
    }
}
