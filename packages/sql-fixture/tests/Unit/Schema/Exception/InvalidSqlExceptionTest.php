<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
final class InvalidSqlExceptionTest extends TestCase
{
    public function testDescribesInvalidSql(): void
    {
        $exception = new \SqlFixture\Schema\Exception\InvalidSqlException('SELECT 1', 'Not a CREATE TABLE');
        self::assertSame('Failed to parse SQL: Not a CREATE TABLE. SQL: SELECT 1', $exception->getMessage());
        self::assertSame('SELECT 1', $exception->sql);
        self::assertSame('Not a CREATE TABLE', $exception->reason);
    }
}
