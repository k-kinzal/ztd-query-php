<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
final class UnsupportedManyToManyExceptionTest extends TestCase
{
    public function testDescribesUnsupportedManyToMany(): void
    {
        $exception = new \SqlFixture\Plan\Exception\UnsupportedManyToManyException('order.id <> product.id');
        $message = $exception->getMessage();

        self::assertSame(
            'The <> operator is not supported, because a fixture has to put rows in the '
            . 'junction table and so must name it. Write the two halves instead, for example '
            . '"order.id < order_detail.order_id, order_detail.product_id > product.id". '
            . 'Plan: order.id <> product.id',
            $message
        );
        self::assertSame('order.id <> product.id', $exception->plan);
    }
}
