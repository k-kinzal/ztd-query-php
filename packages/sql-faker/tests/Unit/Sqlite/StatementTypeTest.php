<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\StatementType;

#[CoversNothing]
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
