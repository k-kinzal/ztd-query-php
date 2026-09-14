<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
final class MissingEndpointColumnsExceptionTest extends TestCase
{
    public function testDescribesMissingEndpointColumns(): void
    {
        $exception = new \SqlFixture\Plan\Exception\MissingEndpointColumnsException('order');
        self::assertSame(
            'The endpoint for table order names no columns.',
            $exception->getMessage()
        );
        self::assertSame('order', $exception->table);
    }
}
