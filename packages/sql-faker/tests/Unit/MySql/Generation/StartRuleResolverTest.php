<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Grammar\Resource\SqlVersion;
use SqlFaker\Grammar\Resource\SqlVersionRegistry;
use SqlFaker\MySql\Generation\StartRuleResolver;
use SqlFaker\MySql\Grammar\MySqlGrammar;

#[CoversClass(StartRuleResolver::class)]
#[UsesClass(Grammar::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(NonTerminal::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(MySqlGrammar::class)]
final class StartRuleResolverTest extends TestCase
{
    public function testStartSymbolForTakesARuleThisReleaseDeclaresAsItStands(): void
    {
        $grammar = MySqlGrammar::load('mysql-8.4.7');

        self::assertSame('select_stmt', (new StartRuleResolver($grammar))->startSymbolFor('select_stmt'));
    }

    public function testStartSymbolForFallsBackToTheEntryPointOfTheReleaseItWasAskedOf(): void
    {
        $grammar = new Grammar('start', ['start' => new ProductionRule('start', [new Production([new Terminal('A')])])]);

        self::assertSame('start', (new StartRuleResolver($grammar))->startSymbolFor(null));
    }

    public function testStartSymbolForHandsBackARequestNothingMatches(): void
    {
        $grammar = new Grammar('start', ['start' => new ProductionRule('start', [new Production([new Terminal('A')])])]);

        self::assertSame('no_such_rule', (new StartRuleResolver($grammar))->startSymbolFor('no_such_rule'));
    }
    public function testStartSymbolForMapsModernRequestsToLegacyGrammarRules(): void
    {
        $grammar = MySqlGrammar::load('mysql-5.6.51');
        $resolver = new StartRuleResolver($grammar);

        self::assertSame('select', $resolver->startSymbolFor('select_stmt'));
        self::assertSame('insert', $resolver->startSymbolFor('insert_stmt'));
        self::assertSame('update', $resolver->startSymbolFor('update_stmt'));
        self::assertSame('delete', $resolver->startSymbolFor('delete_stmt'));
        self::assertSame($grammar->startSymbol, $resolver->startSymbolFor('simple_statement_or_begin'));
    }
    /**
     * @return list<array{string}>
     */
    public static function providerEntryVersions(): array
    {
        return [['mysql-5.6.51'], ['mysql-8.4.7']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('providerEntryVersions')]
    public function testStartSymbolForPreservesGrammarEntryEvenWhenStatementRulesExist(string $version): void
    {
        $grammar = MySqlGrammar::load($version);
        self::assertSame($grammar->startSymbol, (new StartRuleResolver($grammar))->startSymbolFor(null));
    }
}
