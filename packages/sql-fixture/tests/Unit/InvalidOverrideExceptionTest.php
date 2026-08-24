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

        self::assertSame(
            'Cannot override order.staus: there is no such column. Its columns are: id, status.',
            $message
        );
    }

    #[Test]
    public function notNullableSaysWhyNullIsRefused(): void
    {
        $schema = new TableSchema('order', ['status' => new ColumnDefinition('status', 'VARCHAR', nullable: false)]);

        $message = InvalidOverrideException::notNullable('status', $schema)->getMessage();

        self::assertSame('Cannot override order.status with null: the column is NOT NULL.', $message);
    }

    #[Test]
    public function generatedColumnSaysTheDatabaseComputesIt(): void
    {
        $schema = new TableSchema('order', ['code' => new ColumnDefinition('code', 'VARCHAR', generated: true)]);

        $message = InvalidOverrideException::generatedColumn('code', $schema)->getMessage();

        self::assertSame(
            'Cannot override order.code: the database computes it, so a value written here '
            . 'would be rejected on insert.',
            $message
        );
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
