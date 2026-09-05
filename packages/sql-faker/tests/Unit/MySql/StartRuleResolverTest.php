<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\SqlVersionRegistry;
use SqlFaker\Grammar\Terminal;
use SqlFaker\MySql\Grammar\MySqlGrammar;
use SqlFaker\MySql\StartRuleResolver;

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
}
