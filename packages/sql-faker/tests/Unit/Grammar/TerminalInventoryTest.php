<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminalInventory;

#[CoversClass(TerminalInventory::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
final class TerminalInventoryTest extends TestCase
{
    public function testIncludesDeclaredTerminalsAndUnknownLexerSymbols(): void
    {
        $grammar = new Grammar('start', [
            'start' => new ProductionRule('start', [
                new Production([new Terminal('SELECT'), new NonTerminal('value')]),
            ]),
        ]);

        self::assertSame(['SELECT', 'value'], TerminalInventory::fromGrammar($grammar));
    }

    public function testDeduplicatesAndSortsWithoutIncludingDefinedRules(): void
    {
        $grammar = new Grammar('start', [
            'start' => new ProductionRule('start', [
                new Production([
                    new Terminal('Z_TOKEN'),
                    new NonTerminal('nested'),
                    new Terminal('A_TOKEN'),
                    new Terminal('Z_TOKEN'),
                ]),
            ]),
            'nested' => new ProductionRule('nested', [new Production([])]),
        ]);

        self::assertSame(['A_TOKEN', 'Z_TOKEN'], TerminalInventory::fromGrammar($grammar));
    }
}
