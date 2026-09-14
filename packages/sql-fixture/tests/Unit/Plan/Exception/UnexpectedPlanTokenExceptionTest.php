<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
final class UnexpectedPlanTokenExceptionTest extends TestCase
{
    public function testDescribesUnexpectedPlanToken(): void
    {
        $exception = new \SqlFixture\Plan\Exception\UnexpectedPlanTokenException('order.id ! x.y', 9, "one of '<', '>' or '-'");
        $message = $exception->getMessage();

        self::assertSame(
            "Cannot parse the fixture plan at offset 9: expected one of '<', '>' or '-'. "
            . 'Plan: order.id ! x.y',
            $message
        );
        self::assertSame('order.id ! x.y', $exception->plan);
        self::assertSame(9, $exception->offset);
        self::assertSame("one of '<', '>' or '-'", $exception->expected);
    }
}
