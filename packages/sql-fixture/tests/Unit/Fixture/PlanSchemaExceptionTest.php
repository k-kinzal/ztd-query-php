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

        self::assertStringContainsString('The plan links order.idd', $message);
        self::assertStringContainsString('order has no column idd', $message);
        self::assertStringContainsString('Its columns are: id, status.', $message);
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
}
