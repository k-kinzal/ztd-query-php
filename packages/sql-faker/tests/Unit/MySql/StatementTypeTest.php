<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\StatementType;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StatementType::class)]
final class StatementTypeTest extends TestCase
{
    public function testSelectHasExpectedValue(): void
    {
        self::assertSame('select_stmt', StatementType::Select->value);
    }

    public function testCasesCount(): void
    {
        self::assertCount(8, StatementType::cases());
    }
}
