<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;

#[CoversClass(\SqlFixture\Fixture\Exception\MissingRelationValueException::class)]
#[UsesClass(\SqlFixture\Fixture\PlanSchemaException::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\GeneratedColumnReferenceException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\UnknownPlanColumnException::class)]
final class MissingRelationValueExceptionTest extends TestCase
{
    public function testDescribesMissingRelationValue(): void
    {
        $exception = new \SqlFixture\Fixture\Exception\MissingRelationValueException('order_id', ColumnRef::of('order', 'id'), 'id');
        $message = $exception->getMessage();

        self::assertSame('Cannot fill order_id: the generated order row has no id to copy from.', $message);
        self::assertSame('order_id', $exception->childColumn);
        self::assertEquals(ColumnRef::of('order', 'id'), $exception->parent);
        self::assertSame('id', $exception->parentColumn);
    }
}
