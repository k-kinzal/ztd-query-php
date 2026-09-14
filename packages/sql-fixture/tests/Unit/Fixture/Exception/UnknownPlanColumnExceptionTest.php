<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(\SqlFixture\Fixture\Exception\UnknownPlanColumnException::class)]
#[UsesClass(\SqlFixture\Fixture\PlanSchemaException::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\GeneratedColumnReferenceException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\MissingRelationValueException::class)]
final class UnknownPlanColumnExceptionTest extends TestCase
{
    public function testDescribesUnknownPlanColumn(): void
    {
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT'),
            'status' => new ColumnDefinition('status', 'VARCHAR'),
        ]);
        $exception = new \SqlFixture\Fixture\Exception\UnknownPlanColumnException(ColumnRef::of('order', 'idd'), 'idd', $schema);


        $message = $exception->getMessage();

        self::assertSame(
            'The plan links order.idd, but order has no column idd. Its columns are: id, status.',
            $message
        );
        self::assertEquals(ColumnRef::of('order', 'idd'), $exception->reference);
        self::assertSame('idd', $exception->column);
        self::assertSame($schema, $exception->schema);
    }
}
