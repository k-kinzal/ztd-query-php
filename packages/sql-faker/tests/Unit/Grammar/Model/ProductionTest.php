<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(Production::class)]
#[CoversClass(Terminal::class)]
#[CoversClass(NonTerminal::class)]
final class ProductionTest extends TestCase
{
    public function testExposesTheSymbolsItWasBuiltFrom(): void
    {
        $symbols = [new Terminal('A'), new NonTerminal('b')];
        $production = new Production($symbols);

        self::assertSame($symbols, $production->symbols);
    }

    public function testExposesAnEmptySymbolListForAnEmptyProduction(): void
    {
        $production = new Production([]);

        self::assertSame([], $production->symbols);
    }

    public function testSymbolsAreAccessible(): void
    {
        $terminal = new Terminal('X');
        $production = new Production([$terminal]);

        self::assertSame($terminal, $production->symbols[0]);
    }

    public function testHasTerminalFindsATerminalTheProductionWrites(): void
    {
        self::assertTrue((new Production([new NonTerminal('a'), new Terminal('WITHIN')]))->hasTerminal('WITHIN'));
    }

    public function testHasTerminalRejectsATerminalTheProductionDoesNotWrite(): void
    {
        self::assertFalse((new Production([new NonTerminal('WITHIN')]))->hasTerminal('WITHIN'));
    }

    public function testHasNonTerminalFindsARuleTheProductionExpandsTo(): void
    {
        self::assertTrue((new Production([new Terminal('A'), new NonTerminal('frame_opt')]))->hasNonTerminal('frame_opt'));
    }

    public function testHasNonTerminalRejectsARuleTheProductionDoesNotExpandTo(): void
    {
        self::assertFalse((new Production([new Terminal('frame_opt')]))->hasNonTerminal('frame_opt'));
    }

    public function testHasAnyTerminalReportsAProductionThatWritesSomethingOfItsOwn(): void
    {
        self::assertTrue((new Production([new NonTerminal('a'), new Terminal('OVER')]))->hasAnyTerminal());
    }

    public function testHasAnyTerminalRejectsAProductionMadeOnlyOfRules(): void
    {
        self::assertFalse((new Production([new NonTerminal('a'), new NonTerminal('b')]))->hasAnyTerminal());
    }

    public function testTerminalAtAnswersTheTerminalAtThatPosition(): void
    {
        $terminal = new Terminal('TABLE');

        self::assertSame($terminal, (new Production([new Terminal('DROP'), $terminal]))->terminalAt(1));
    }

    public function testTerminalAtAnswersNothingForANonTerminalOrAPositionPastTheEnd(): void
    {
        $production = new Production([new NonTerminal('a')]);

        self::assertNull($production->terminalAt(0));
        self::assertNull($production->terminalAt(5));
    }

    public function testNonTerminalAtAnswersTheRuleAtThatPosition(): void
    {
        $nonTerminal = new NonTerminal('insert_cmd');

        self::assertSame($nonTerminal, (new Production([new NonTerminal('with'), $nonTerminal]))->nonTerminalAt(1));
    }

    public function testNonTerminalAtAnswersNothingForATerminalOrAPositionPastTheEnd(): void
    {
        $production = new Production([new Terminal('A')]);

        self::assertNull($production->nonTerminalAt(0));
        self::assertNull($production->nonTerminalAt(5));
    }

    public function testNonTerminalNamesListsTheRulesInTheOrderTheyAreWritten(): void
    {
        $production = new Production([new NonTerminal('nm'), new Terminal('AS'), new NonTerminal('frame_opt')]);

        self::assertSame(['nm', 'frame_opt'], $production->nonTerminalNames());
    }
}
