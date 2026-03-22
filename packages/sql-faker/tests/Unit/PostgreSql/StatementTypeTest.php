<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\StatementType;

#[CoversNothing]
final class StatementTypeTest extends TestCase
{
    public function testSelectHasExpectedValue(): void
    {
        self::assertSame('SelectStmt', StatementType::Select->value);
    }

    public function testCasesCount(): void
    {
        self::assertCount(8, StatementType::cases());
    }
}
