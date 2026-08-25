<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lemon;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Sqlite\Lemon\LemonRules;
use SqlFaker\Sqlite\Lemon\LemonSymbols;
use SqlFaker\Sqlite\Lemon\LemonText;

#[CoversClass(LemonRules::class)]
#[UsesClass(LemonSymbols::class)]
#[UsesClass(LemonText::class)]
final class LemonRulesTest extends TestCase
{
    public function testReadFromGathersTheAlternativesOfOneRuleWrittenOnSeveralLines(): void
    {
        self::assertSame(
            ['cmd' => [['SELECT'], ['INSERT']]],
            (new LemonRules())->readFrom("cmd ::= SELECT.\ncmd ::= INSERT.\n", new LemonSymbols()),
        );
    }

    public function testReadFromReadsARuleThatWritesNothing(): void
    {
        self::assertSame(['opt' => [[]]], (new LemonRules())->readFrom("opt ::= .\n", new LemonSymbols()));
    }

    public function testReadFromDropsTheAliasASymbolCarries(): void
    {
        self::assertSame(
            ['cmd' => [['expr']]],
            (new LemonRules())->readFrom("cmd(A) ::= expr(X).\n", new LemonSymbols()),
        );
    }

    public function testReadFromTellsTheSymbolTableWhatEachNameTurnedOutToBe(): void
    {
        $symbols = new LemonSymbols();
        (new LemonRules())->readFrom("cmd ::= SELECT expr.\n", $symbols);

        self::assertTrue($symbols->isTerminal('SELECT'));
        self::assertFalse($symbols->isTerminal('cmd'));
    }

    public function testAlternativesMultipliesOutEachAlternationPosition(): void
    {
        self::assertSame(
            [['A', 'C'], ['A', 'D'], ['B', 'C'], ['B', 'D']],
            (new LemonRules())->alternatives('A|B C|D', new LemonSymbols()),
        );
    }

    public function testAlternativesIgnoresAPositionThatConfiguresTheParser(): void
    {
        self::assertSame([['expr']], (new LemonRules())->alternatives('expr %prec', new LemonSymbols()));
    }

    public function testWithoutAliasDropsWhatTheParserNamesTheValue(): void
    {
        self::assertSame('expr', (new LemonRules())->withoutAlias('expr(A)'));
    }

    public function testWithoutAliasLeavesASymbolWithNoAliasAlone(): void
    {
        self::assertSame('expr', (new LemonRules())->withoutAlias('expr'));
    }
}
