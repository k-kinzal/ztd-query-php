<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\SchemaParseException;

#[CoversClass(SchemaParseException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
final class SchemaParseExceptionTest extends TestCase
{
    #[Test]
    public function testInvalidSql(): void
    {
        $exception = new \SqlFixture\Schema\Exception\InvalidSqlException('SELECT 1', 'Not a CREATE TABLE');
        self::assertSame('Failed to parse SQL: Not a CREATE TABLE. SQL: SELECT 1', $exception->getMessage());
    }

    #[Test]
    public function testNotCreateTable(): void
    {
        $exception = new \SqlFixture\Schema\Exception\ExpectedCreateTableException('SELECT 1');
        self::assertSame('Expected CREATE TABLE statement, got: SELECT 1', $exception->getMessage());
    }

    #[Test]
    public function testNoColumns(): void
    {
        $exception = new \SqlFixture\Schema\Exception\MissingColumnDefinitionsException('users');
        self::assertSame('No columns found in table: users', $exception->getMessage());
    }
}
