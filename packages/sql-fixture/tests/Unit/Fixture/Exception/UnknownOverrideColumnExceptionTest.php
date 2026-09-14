<?php

declare(strict_types=1);

namespace Tests\Unit\Fixture\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;
use SqlFixture\Schema\TableSchema;

#[CoversClass(\SqlFixture\Fixture\Exception\UnknownOverrideColumnException::class)]
#[UsesClass(\SqlFixture\InvalidOverrideException::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(TableSchema::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\NullOverrideException::class)]
#[UsesClass(\SqlFixture\Fixture\Exception\GeneratedColumnOverrideException::class)]
final class UnknownOverrideColumnExceptionTest extends TestCase
{
    public function testDescribesUnknownOverrideColumn(): void
    {
        $schema = new TableSchema('order', [
            'id' => new ColumnDefinition('id', 'INT'),
            'status' => new ColumnDefinition('status', 'VARCHAR'),
        ]);
        $exception = new \SqlFixture\Fixture\Exception\UnknownOverrideColumnException('staus', $schema);


        $message = $exception->getMessage();

        self::assertSame(
            'Cannot override order.staus: there is no such column. Its columns are: id, status.',
            $message
        );
        self::assertSame('staus', $exception->column);
        self::assertSame($schema, $exception->schema);
    }
}
