<?php

declare(strict_types=1);

namespace Tests\Unit\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlCatalog\Catalog\FindingRule;
use SqlCatalog\Catalog\Severity;

#[CoversClass(FindingRule::class)]
#[UsesClass(Severity::class)]
final class FindingRuleTest extends TestCase
{
    #[DataProvider('providerSeverity')]
    public function testSeverity(FindingRule $rule, Severity $expected): void
    {
        self::assertSame($expected, $rule->severity());
    }

    /**
     * @return list<array{FindingRule, Severity}>
     */
    public static function providerSeverity(): array
    {
        return [
            [FindingRule::ExternalInput, Severity::High],
            [FindingRule::DynamicSql, Severity::Medium],
            [FindingRule::PlaceholderCountMismatch, Severity::Medium],
            [FindingRule::UnresolvedSql, Severity::Low],
        ];
    }

    public function testDescribeExplainsEveryRule(): void
    {
        $descriptions = array_map(
            static fn (FindingRule $rule): string => $rule->describe(),
            FindingRule::cases(),
        );
        self::assertCount(count(FindingRule::cases()), array_unique($descriptions));
        self::assertStringContainsString('external input', FindingRule::ExternalInput->describe());
    }
}
