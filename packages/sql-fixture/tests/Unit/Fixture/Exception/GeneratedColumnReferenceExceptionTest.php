<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(\SqlFixture\Fixture\Exception\GeneratedColumnReferenceException::class)]
#[UsesClass(\SqlFixture\Fixture\PlanSchemaException::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\MissingRelationValueException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\UnknownPlanColumnException::class)]
final class GeneratedColumnReferenceExceptionTest extends TestCase
{
    public function testDescribesGeneratedColumnReference(): void
    {
        $schema = new TableSchema('order', ['code' => new ColumnDefinition('code', 'VARCHAR', generated: true)]);
        $exception = new \SqlFixture\Fixture\Exception\GeneratedColumnReferenceException(ColumnRef::of('order', 'code'), 'code', $schema);


        $message = $exception->getMessage();

        self::assertSame(
            'The plan links order.code, but order.code is a generated column: the database '
            . 'computes it, so there is no value to carry across the relation and none to write '
            . 'into it. Link a stored column instead.',
            $message
        );
        self::assertEquals(ColumnRef::of('order', 'code'), $exception->reference);
        self::assertSame('code', $exception->column);
        self::assertSame($schema, $exception->schema);
    }
}
