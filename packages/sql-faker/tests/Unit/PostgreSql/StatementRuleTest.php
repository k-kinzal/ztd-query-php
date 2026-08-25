<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\StatementRule;

#[CoversClass(StatementRule::class)]
final class StatementRuleTest extends TestCase
{
    public function testSelectHasExpectedValue(): void
    {
        self::assertSame('SelectStmt', StatementRule::Select->value);
    }

    public function testCreateTableAsHasExpectedValue(): void
    {
        self::assertSame('CreateAsStmt', StatementRule::CreateTableAs->value);
    }

    public function testCreateDomainHasExpectedValue(): void
    {
        self::assertSame('CreateDomainStmt', StatementRule::CreateDomain->value);
    }

    public function testCasesCount(): void
    {
        self::assertCount(10, StatementRule::cases());
    }
}
