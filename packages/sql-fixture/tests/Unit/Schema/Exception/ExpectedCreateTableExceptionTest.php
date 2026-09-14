<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlFixture\Schema\Exception\ExpectedCreateTableException::class)]
#[UsesClass(\SqlFixture\Schema\SchemaParseException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\InvalidSqlException::class)]
#[UsesClass(\SqlFixture\Schema\Exception\MissingColumnDefinitionsException::class)]
final class ExpectedCreateTableExceptionTest extends TestCase
{
    public function testDescribesExpectedCreateTable(): void
    {
        $exception = new \SqlFixture\Schema\Exception\ExpectedCreateTableException('SELECT 1');
        self::assertSame('Expected CREATE TABLE statement, got: SELECT 1', $exception->getMessage());
        self::assertSame('SELECT 1', $exception->sql);
    }
}
