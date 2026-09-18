<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
#[UsesClass(\SqlFixture\Plan\Choice\ChoiceValidation::class)]
#[UsesClass(\SqlFixture\Plan\Choice\PlanContents::class)]
final class InvalidTableNameExceptionTest extends TestCase
{
    public function testDescribesInvalidTableName(): void
    {
        $exception = new \SqlFixture\Plan\Exception\InvalidTableNameException('order.id < order_detail.order_id');
        $message = $exception->getMessage();

        self::assertSame(
            'A FixturePlan part must be a Relation or a plain table name, but '
            . '"order.id < order_detail.order_id" is neither. To build a plan from relation '
            . 'syntax, use FixturePlan::from().',
            $message
        );
        self::assertSame('order.id < order_detail.order_id', $exception->part);
    }
}
