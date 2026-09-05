<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\StatementRule;

#[CoversClass(StatementRule::class)]
final class StatementRuleTest extends TestCase
{
    public function testSelectHasExpectedValue(): void
    {
        self::assertSame('select', StatementRule::Select->value);
    }

    public function testCasesCount(): void
    {
        self::assertCount(8, StatementRule::cases());
    }
}
