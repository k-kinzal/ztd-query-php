<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminalInventory;

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
                new Production([new Terminal('SELECT_SYM'), new NonTerminal('value')]),
            ]),
        ]);

        self::assertSame(['SELECT_SYM', 'value'], TerminalInventory::fromGrammar($grammar));
    }

    public function testDeduplicatesAndSortsWithoutIncludingDefinedRules(): void
    {
        $grammar = new Grammar('start', [
            'start' => new ProductionRule('start', [
                new Production([
                    new Terminal('Z_SYM'),
                    new NonTerminal('nested'),
                    new Terminal('A_SYM'),
                    new Terminal('Z_SYM'),
                ]),
            ]),
            'nested' => new ProductionRule('nested', [new Production([])]),
        ]);

        self::assertSame(['A_SYM', 'Z_SYM'], TerminalInventory::fromGrammar($grammar));
    }

    public function testFromGrammarNamesEveryTerminalTheRulesReach(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT'), new NonTerminal('nm')])]),
            'nm' => new ProductionRule('nm', [new Production([new Terminal('IDENT')])]),
        ]);

        self::assertSame(['IDENT', 'SELECT'], TerminalInventory::fromGrammar($grammar));
    }
}
