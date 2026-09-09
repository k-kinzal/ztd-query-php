<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\PostgreSql\StatementRule;
use SqlFaker\PostgreSql\StatementType;

#[CoversClass(StatementType::class)]
#[UsesClass(StatementRule::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationNode::class)]
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
