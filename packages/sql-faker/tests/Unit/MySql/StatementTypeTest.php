<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Generation\StatementRule;
use SqlFaker\MySql\StatementType;

#[CoversClass(StatementType::class)]
#[UsesClass(StatementRule::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\CompletionCosts::class)]
#[UsesClass(\SqlFaker\Generation\Derivation\DerivationNode::class)]
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
