<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
final class EmptyPlanExceptionTest extends TestCase
{
    public function testDescribesEmptyPlan(): void
    {
        $exception = new \SqlFixture\Plan\Exception\EmptyPlanException();
        self::assertSame(
            'A fixture plan must name at least one table.',
            $exception->getMessage()
        );
    }
}
