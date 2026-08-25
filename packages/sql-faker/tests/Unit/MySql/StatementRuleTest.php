<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\StatementRule;

#[CoversClass(StatementRule::class)]
final class StatementRuleTest extends TestCase
{
    public function testSelectHasExpectedValue(): void
    {
        self::assertSame('select_stmt', StatementRule::Select->value);
    }

    public function testCasesCount(): void
    {
        self::assertCount(8, StatementRule::cases());
    }
}
