<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\StatementType;

#[CoversClass(StatementType::class)]
final class StatementTypeTest extends TestCase
{
    public function testSelectHasExpectedValue(): void
    {
        self::assertSame('select', StatementType::Select->value);
    }

    public function testCasesCount(): void
    {
        self::assertCount(8, StatementType::cases());
    }
}
