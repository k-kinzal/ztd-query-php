<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\StatementType;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StatementType::class)]
final class StatementTypeTest extends TestCase
{
    public function testSelectHasExpectedValue(): void
    {
        self::assertSame('SelectStmt', StatementType::Select->value);
    }

    public function testCreateTableAsHasExpectedValue(): void
    {
        self::assertSame('CreateAsStmt', StatementType::CreateTableAs->value);
    }

    public function testCreateDomainHasExpectedValue(): void
    {
        self::assertSame('CreateDomainStmt', StatementType::CreateDomain->value);
    }

    public function testCasesCount(): void
    {
        self::assertCount(10, StatementType::cases());
    }
}
