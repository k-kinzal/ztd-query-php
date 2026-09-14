<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(\SqlFixture\Fixture\Exception\NullOverrideException::class)]
#[UsesClass(\SqlFixture\InvalidOverrideException::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\UnknownOverrideColumnException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\GeneratedColumnOverrideException::class)]
final class NullOverrideExceptionTest extends TestCase
{
    public function testDescribesNullOverride(): void
    {
        $schema = new TableSchema('order', ['status' => new ColumnDefinition('status', 'VARCHAR', nullable: false)]);
        $exception = new \SqlFixture\Fixture\Exception\NullOverrideException('status', $schema);


        $message = $exception->getMessage();

        self::assertSame('Cannot override order.status with null: the column is NOT NULL.', $message);
        self::assertSame('status', $exception->column);
        self::assertSame($schema, $exception->schema);
    }
}
