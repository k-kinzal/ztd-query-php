<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;

#[CoversClass(Grammar::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(NonTerminal::class)]
#[UsesClass(SqlVersion::class)]
final class GrammarTest extends TestCase
{
    public function testLoad(): void
    {
        $grammar = Grammar::load();

        self::assertSame('start_entry', $grammar->startSymbol);
        self::assertGreaterThan(100, count($grammar->ruleMap), 'MySQL grammar should have many rules');
    }

    public function testLoadWithDefaultVersion(): void
    {
        $grammar = Grammar::load(null);

        self::assertSame('start_entry', $grammar->startSymbol);
    }

    public function testResolveVersionUsesExactConfiguredDefault(): void
    {
        self::assertSame('mysql-8.4.7', Grammar::resolveVersion());
        self::assertSame('mysql-5.7.44', Grammar::resolveVersion('mysql-5.7.44'));
    }

    public function testLoadWithExplicitVersion(): void
    {
        $grammar = Grammar::load('mysql-8.4.7');

        self::assertSame('start_entry', $grammar->startSymbol);
        self::assertGreaterThan(100, count($grammar->ruleMap));
    }

    public function testLoadWithNonExistentVersionThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported mysql version');

        Grammar::load('non-existent-version');
    }

    public function testLoadedGrammarHasExpectedStructure(): void
    {
        $grammar = Grammar::load();

        self::assertArrayHasKey('select_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('insert_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('update_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('delete_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('create_table_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('alter_table_stmt', $grammar->ruleMap);
        self::assertArrayHasKey('drop_table_stmt', $grammar->ruleMap);

        $selectRule = $grammar->ruleMap['select_stmt'];
        self::assertSame('select_stmt', $selectRule->lhs);
        self::assertGreaterThanOrEqual(1, count($selectRule->alternatives));
    }

    public function testLoadedGrammarVersionsProduceDifferentRuleCounts(): void
    {
        $grammar56 = Grammar::load('mysql-5.6.51');
        $grammar84 = Grammar::load('mysql-8.4.7');

        self::assertNotSame(count($grammar56->ruleMap), count($grammar84->ruleMap));
    }

    public function testStartSymbolForTakesARuleThisReleaseDeclaresAsItStands(): void
    {
        $grammar = Grammar::load('mysql-8.4.7');

        self::assertSame('select_stmt', $grammar->startSymbolFor('select_stmt'));
    }

    public function testStartSymbolForFallsBackToTheEntryPointOfTheReleaseItWasAskedOf(): void
    {
        $grammar = new Grammar('start', ['start' => new ProductionRule('start', [new Production([new Terminal('A')])])]);

        self::assertSame('start', $grammar->startSymbolFor(null));
    }

    public function testStartSymbolForHandsBackARequestNothingMatches(): void
    {
        $grammar = new Grammar('start', ['start' => new ProductionRule('start', [new Production([new Terminal('A')])])]);

        self::assertSame('no_such_rule', $grammar->startSymbolFor('no_such_rule'));
    }
}
