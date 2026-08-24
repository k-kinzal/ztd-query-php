<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\InvalidOverrideException;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(InvalidOverrideException::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(ColumnDefinition::class)]
final class InvalidOverrideExceptionTest extends TestCase
{
    #[Test]
    public function unknownColumnListsWhatTheTableHas(): void
    {
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT'),
            'status' => new ColumnDefinition('status', 'VARCHAR'),
        ]);

        $message = InvalidOverrideException::unknownColumn('staus', $schema)->getMessage();

        self::assertStringContainsString('Cannot override order.staus', $message);
        self::assertStringContainsString('Its columns are: id, status.', $message);
    }

    #[Test]
    public function notNullableSaysWhyNullIsRefused(): void
    {
        $schema = new TableSchema('order', ['status' => new ColumnDefinition('status', 'VARCHAR', nullable: false)]);

        $message = InvalidOverrideException::notNullable('status', $schema)->getMessage();

        self::assertStringContainsString('Cannot override order.status with null', $message);
        self::assertStringContainsString('NOT NULL', $message);
    }

    #[Test]
    public function generatedColumnSaysTheDatabaseComputesIt(): void
    {
        $schema = new TableSchema('order', ['code' => new ColumnDefinition('code', 'VARCHAR', generated: true)]);

        $message = InvalidOverrideException::generatedColumn('code', $schema)->getMessage();

        self::assertStringContainsString('Cannot override order.code', $message);
        self::assertStringContainsString('rejected on insert', $message);
    }

    #[Test]
    public function isInvalidArgumentException(): void
    {
        $schema = new TableSchema('order', ['id' => new ColumnDefinition('id', 'INT')]);

        self::assertInstanceOf(
            \InvalidArgumentException::class,
            InvalidOverrideException::unknownColumn('x', $schema)
        );
    }
}
