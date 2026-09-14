<?php

declare(strict_types=1);

namespace Tests\Unit\Plan\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;

#[CoversClass(\SqlFixture\Plan\Exception\CompositeArityMismatchException::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(\SqlFixture\Plan\PlanSyntaxException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyPlanException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\EmptyTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\MissingEndpointColumnsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\InvalidTableNameException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnbalancedBracketsException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnexpectedPlanTokenException::class)]
#[UsesClass(\SqlFixture\Plan\Exception\UnsupportedManyToManyException::class)]
final class CompositeArityMismatchExceptionTest extends TestCase
{
    public function testDescribesCompositeArityMismatch(): void
    {
        $exception = new \SqlFixture\Plan\Exception\CompositeArityMismatchException(
            ColumnRef::of('order', 'shop_id', 'no'),
            ColumnRef::of('order_detail', 'order_no')
        );
        $message = $exception->getMessage();

        self::assertSame(
            'The relation order.(shop_id, no) ... order_detail.order_no names 2 columns on '
            . 'one side and 1 on the other.',
            $message
        );
        self::assertEquals(ColumnRef::of('order', 'shop_id', 'no'), $exception->left);
        self::assertEquals(ColumnRef::of('order_detail', 'order_no'), $exception->right);
    }
}
