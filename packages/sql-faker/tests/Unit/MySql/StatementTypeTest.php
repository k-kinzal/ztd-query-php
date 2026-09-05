<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\StatementRule;
use SqlFaker\MySql\StatementType;

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
