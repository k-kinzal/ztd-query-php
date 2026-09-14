<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
final class EmptyTableNameExceptionTest extends TestCase
{
    public function testDescribesEmptyTableName(): void
    {
        $exception = new \SqlFixture\Plan\Exception\EmptyTableNameException();
        self::assertSame(
            'A relation endpoint must name a table.',
            $exception->getMessage()
        );
    }
}
