<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\SchemaParseException;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SchemaParseException::class)]
final class SchemaParseExceptionTest extends TestCase
{
    #[Test]
    public function invalidSql(): void
    {
        $exception = SchemaParseException::invalidSql('SELECT 1', 'Not a CREATE TABLE');
        self::assertSame('Failed to parse SQL: Not a CREATE TABLE. SQL: SELECT 1', $exception->getMessage());
    }

    #[Test]
    public function notCreateTable(): void
    {
        $exception = SchemaParseException::notCreateTable('SELECT 1');
        self::assertSame('Expected CREATE TABLE statement, got: SELECT 1', $exception->getMessage());
    }

    #[Test]
    public function noColumns(): void
    {
        $exception = SchemaParseException::noColumns('users');
        self::assertSame('No columns found in table: users', $exception->getMessage());
    }
}
