<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Fixture\PlanSchemaException;
use SqlFixture\Plan\ColumnRef;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(PlanSchemaException::class)]
#[UsesClass(ColumnRef::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
final class PlanSchemaExceptionTest extends TestCase
{
    #[Test]
    public function namesTheLinkTheTableAndWhatItDoesHave(): void
    {
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT'),
            'status' => new ColumnDefinition('status', 'VARCHAR'),
        ]);

        $message = PlanSchemaException::unknownColumn(ColumnRef::of('order', 'idd'), 'idd', $schema)->getMessage();

        self::assertSame(
            'The plan links order.idd, but order has no column idd. Its columns are: id, status.',
            $message
        );
    }

    #[Test]
    public function isRuntimeException(): void
    {
        $schema = new TableSchema('order', ['id' => new ColumnDefinition('id', 'INT')]);

        self::assertInstanceOf(
            \RuntimeException::class,
            PlanSchemaException::unknownColumn(ColumnRef::of('order', 'x'), 'x', $schema)
        );
    }

    #[Test]
    public function generatedColumnExplainsWhyItCannotCarryAValue(): void
    {
        $schema = new TableSchema('order', ['code' => new ColumnDefinition('code', 'VARCHAR', generated: true)]);

        $message = PlanSchemaException::generatedColumn(ColumnRef::of('order', 'code'), 'code', $schema)->getMessage();

        self::assertSame(
            'The plan links order.code, but order.code is a generated column: the database '
            . 'computes it, so there is no value to carry across the relation and none to write '
            . 'into it. Link a stored column instead.',
            $message
        );
    }

    #[Test]
    public function missingValueNamesBothEnds(): void
    {
        $message = PlanSchemaException::missingValue('order_id', ColumnRef::of('order', 'id'), 'id')->getMessage();

        self::assertSame('Cannot fill order_id: the generated order row has no id to copy from.', $message);
    }
}
