<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\StatementRule;
use SqlFaker\Sqlite\StatementType;

#[CoversClass(StatementType::class)]
final class StatementTypeTest extends TestCase
{
    public function testAliasPreservesAllProviderStatementCases(): void
    {
        self::assertSame(StatementRule::cases(), StatementType::cases());
    }

    public function testAliasPreservesBackedValueLookup(): void
    {
        self::assertSame(StatementRule::Select, StatementType::from(StatementRule::Select->value));
    }
}
