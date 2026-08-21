<?php

declare(strict_types=1);

namespace Tests\Unit\Plan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\PlanUndefinedException;

#[CoversClass(PlanUndefinedException::class)]
final class PlanUndefinedExceptionTest extends TestCase
{
    #[Test]
    public function namesTheClassAndTheMethodToOverride(): void
    {
        $exception = PlanUndefinedException::forClass('App\\Fixtures\\OrderPlan');

        self::assertStringContainsString('App\\Fixtures\\OrderPlan::define()', $exception->getMessage());
        self::assertStringContainsString('Override definition()', $exception->getMessage());
    }

    #[Test]
    public function isLogicException(): void
    {
        self::assertInstanceOf(\LogicException::class, PlanUndefinedException::forClass('X'));
    }
}
