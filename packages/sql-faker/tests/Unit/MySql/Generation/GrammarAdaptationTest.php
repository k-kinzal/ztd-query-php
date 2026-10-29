<?php

declare(strict_types=1);

namespace Tests\Unit\MySql\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Coverage\GrammarCoverageInventory;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\MySql\Generation\GrammarAdaptation;
use SqlFaker\MySql\Grammar\MySqlGrammar;

#[CoversClass(GrammarAdaptation::class)]
#[Medium]
final class GrammarAdaptationTest extends TestCase
{
    /**
     * @param list<string> $excludedRules
     */
    #[DataProvider('providerLegacyVersions')]
    public function testAdaptedExcludesOnlyAlternativesUnavailableToClients(string $version, array $excludedRules, int $removed): void
    {
        $original = MySqlGrammar::load($version);
        $adapted = (new GrammarAdaptation())->adapted($original);
        $inventory = new GrammarCoverageInventory($adapted, 'statement', $version, $original);
        $originalInventory = new GrammarCoverageInventory($original, 'statement', $version);
        self::assertSame($original->startSymbol, $adapted->startSymbol);
        self::assertSame(array_keys($original->ruleMap), array_keys($adapted->ruleMap));
        self::assertArrayHasKey('partition_entry', $originalInventory->reachableRules());
        self::assertArrayNotHasKey('partition_entry', $inventory->reachableRules());
        self::assertArrayHasKey('partition', $inventory->reachableRules());
        self::assertSame($original->ruleMap['partition'], $adapted->ruleMap['partition']);
        self::assertSame($original->ruleMap['partition_entry'], $adapted->ruleMap['partition_entry']);
        self::assertSame(array_fill(0, count($excludedRules), 'excluded'), array_column($inventory->adaptations, 'status'));
        self::assertSame($excludedRules, array_map(static fn (string $origin): string => explode('#', $origin)[0], array_column($inventory->adaptations, 'origin')));
        self::assertArrayNotHasKey('parse_gcol_expr', $inventory->reachableRules());
        self::assertSame($original->ruleMap['parse_gcol_expr'] ?? null, $adapted->ruleMap['parse_gcol_expr'] ?? null);
        self::assertCount(1, array_filter($adapted->ruleMap['keyword_sp']->alternatives, static fn (Production $production): bool => $production->hasTerminal('RETURNS_SYM')));
        self::assertCount(count($originalInventory->denominator) - $removed, $inventory->denominator);
    }

    /**
     * @return iterable<string, array{string, list<string>, int}>
     */
    public static function providerLegacyVersions(): iterable
    {
        yield '5.6' => ['mysql-5.6.51', ['statement', 'keyword_sp'], 3];
        yield '5.7' => ['mysql-5.7.44', ['statement', 'statement', 'keyword_sp'], 5];
    }

    public function testAdaptedLeavesModernGrammarUnchanged(): void
    {
        $original = MySqlGrammar::load('mysql-8.4.7');
        self::assertSame($original, (new GrammarAdaptation())->adapted($original));
    }
}
