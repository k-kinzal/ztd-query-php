<?php

declare(strict_types=1);

namespace Tests\Unit\Schema\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFixture\Schema\Exception\UnreadableTableNameException as Subject;
use SqlFixture\Schema\SchemaParseException;

#[CoversClass(Subject::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(SchemaParseException::class)]
final class UnreadableTableNameExceptionTest extends TestCase
{
    public function testItReportsTheNameItCouldNotRead(): void
    {
        $exception = new Subject("users\0");

        self::assertSame("users\0", $exception->tableName);
        self::assertSame("Not a table name: users\0", $exception->getMessage());
    }
}
