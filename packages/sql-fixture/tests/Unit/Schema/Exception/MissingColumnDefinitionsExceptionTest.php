<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\ColumnDefinition;

#[CoversClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
#[UsesClass(ColumnDefinition::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
final class MissingColumnDefinitionsExceptionTest extends TestCase
{
    public function testDescribesMissingColumnDefinitions(): void
    {
        $exception = new \SqlFixture\Schema\Exception\MissingColumnDefinitionsException('users');
        self::assertSame('No columns found in table: users', $exception->getMessage());
        self::assertSame('users', $exception->tableName);
    }
}
