<?php

declare(strict_types=1);

namespace Tests\Unit\Rewrite;

use PHPUnit\Framework\TestCase;
use ZtdQuery\Rewrite\MultiRewritePlan;
use ZtdQuery\Rewrite\QueryKind;
use ZtdQuery\Rewrite\RewritePlan;

final class MultiRewritePlanTest extends TestCase
{
    public function testPlansReturnsAllPlans(): void
    {
        $plan1 = new RewritePlan('SELECT * FROM users', QueryKind::READ);
        $plan2 = new RewritePlan('INSERT INTO users (id) VALUES (1)', QueryKind::WRITE_SIMULATED);
        $plans = [$plan1, $plan2];

        $multiPlan = new MultiRewritePlan($plans);

        $this->assertSame($plans, $multiPlan->plans());
    }

    public function testCountReturnsNumberOfPlans(): void
    {
        $plans = [
            new RewritePlan('SELECT 1', QueryKind::READ),
            new RewritePlan('SELECT 2', QueryKind::READ),
            new RewritePlan('SELECT 3', QueryKind::READ),
        ];

        $multiPlan = new MultiRewritePlan($plans);

        $this->assertSame(3, $multiPlan->count());
    }

    public function testCountReturnsZeroForEmptyPlans(): void
    {
        $multiPlan = new MultiRewritePlan([]);

        $this->assertSame(0, $multiPlan->count());
    }

    public function testAllAllowedReturnsTrueWhenNoForbiddenPlans(): void
    {
        $plans = [
            new RewritePlan('SELECT * FROM users', QueryKind::READ),
            new RewritePlan('INSERT INTO users VALUES (1)', QueryKind::WRITE_SIMULATED),
            new RewritePlan('CREATE TABLE t (id INT)', QueryKind::DDL_SIMULATED),
        ];

        $multiPlan = new MultiRewritePlan($plans);

        $this->assertTrue($multiPlan->allAllowed());
    }

    public function testAllAllowedReturnsFalseWhenForbiddenPlanExists(): void
    {
        $plans = [
            new RewritePlan('SELECT * FROM users', QueryKind::READ),
            new RewritePlan('DROP DATABASE test', QueryKind::FORBIDDEN),
            new RewritePlan('SELECT 1', QueryKind::READ),
        ];

        $multiPlan = new MultiRewritePlan($plans);

        $this->assertFalse($multiPlan->allAllowed());
    }

    public function testAllAllowedReturnsTrueForEmptyPlans(): void
    {
        $multiPlan = new MultiRewritePlan([]);

        $this->assertTrue($multiPlan->allAllowed());
    }

    public function testFirstReturnsFirstPlan(): void
    {
        $plan1 = new RewritePlan('SELECT 1', QueryKind::READ);
        $plan2 = new RewritePlan('SELECT 2', QueryKind::READ);

        $multiPlan = new MultiRewritePlan([$plan1, $plan2]);

        $this->assertSame($plan1, $multiPlan->first());
    }

    public function testFirstReturnsNullForEmptyPlans(): void
    {
        $multiPlan = new MultiRewritePlan([]);

        $this->assertNull($multiPlan->first());
    }

    public function testGetReturnsPlanAtIndex(): void
    {
        $plan1 = new RewritePlan('SELECT 1', QueryKind::READ);
        $plan2 = new RewritePlan('SELECT 2', QueryKind::READ);
        $plan3 = new RewritePlan('SELECT 3', QueryKind::READ);

        $multiPlan = new MultiRewritePlan([$plan1, $plan2, $plan3]);

        $this->assertSame($plan1, $multiPlan->get(0));
        $this->assertSame($plan2, $multiPlan->get(1));
        $this->assertSame($plan3, $multiPlan->get(2));
    }

    public function testGetReturnsNullForInvalidIndex(): void
    {
        $multiPlan = new MultiRewritePlan([
            new RewritePlan('SELECT 1', QueryKind::READ),
        ]);

        $this->assertNull($multiPlan->get(1));
        $this->assertNull($multiPlan->get(-1));
        $this->assertNull($multiPlan->get(100));
    }

    public function testGetReturnsNullForEmptyPlans(): void
    {
        $multiPlan = new MultiRewritePlan([]);

        $this->assertNull($multiPlan->get(0));
    }
}
