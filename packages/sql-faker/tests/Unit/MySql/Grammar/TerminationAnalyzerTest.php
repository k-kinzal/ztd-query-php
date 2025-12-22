<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Grammar;

use PHPUnit\Framework\TestCase;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;

final class TerminationAnalyzerTest extends TestCase
{
    public function testGetMinLengthForTerminalOnlyRule(): void
    {
        $grammar = new Grammar(
            'start',
            [
                'start' => new ProductionRule('start', [
                    new Production([
                        new Terminal('TOKEN'),
                    ]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        self::assertSame(1, $analyzer->getMinLength('start'));
    }

    public function testGetMinLengthForEmptyProduction(): void
    {
        $grammar = new Grammar(
            'opt',
            [
                'opt' => new ProductionRule('opt', [
                    new Production([]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        self::assertSame(0, $analyzer->getMinLength('opt'));
    }

    public function testGetMinLengthChoosesShortestAlternative(): void
    {
        $grammar = new Grammar(
            'expr',
            [
                'expr' => new ProductionRule('expr', [
                    new Production([
                        new Terminal('A'),
                        new Terminal('B'),
                        new Terminal('C'),
                    ]),
                    new Production([
                        new Terminal('X'),
                    ]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        self::assertSame(1, $analyzer->getMinLength('expr'));
    }

    public function testGetMinLengthForNestedRules(): void
    {
        $grammar = new Grammar(
            'a',
            [
                'a' => new ProductionRule('a', [
                    new Production([
                        new NonTerminal('b'),
                    ]),
                ]),
                'b' => new ProductionRule('b', [
                    new Production([
                        new NonTerminal('c'),
                    ]),
                ]),
                'c' => new ProductionRule('c', [
                    new Production([
                        new Terminal('TOKEN'),
                    ]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        self::assertSame(1, $analyzer->getMinLength('a'));
        self::assertSame(1, $analyzer->getMinLength('b'));
        self::assertSame(1, $analyzer->getMinLength('c'));
    }

    public function testGetMinLengthForMultipleTerminals(): void
    {
        $grammar = new Grammar(
            'punct',
            [
                'punct' => new ProductionRule('punct', [
                    new Production([
                        new Terminal(','),
                        new Terminal(';'),
                    ]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        self::assertSame(2, $analyzer->getMinLength('punct'));
    }

    public function testGetMinLengthForUnknownNonTerminal(): void
    {
        $grammar = new Grammar('start', []);

        $analyzer = new TerminationAnalyzer($grammar);

        self::assertSame(1, $analyzer->getMinLength('unknown'));
    }

    public function testEstimateProductionLengthForTerminals(): void
    {
        $grammar = new Grammar('start', []);
        $analyzer = new TerminationAnalyzer($grammar);

        $production = new Production([
            new Terminal('A'),
            new Terminal('B'),
        ]);

        self::assertSame(2, $analyzer->estimateProductionLength($production));
    }

    public function testEstimateProductionLengthForNonTerminals(): void
    {
        $grammar = new Grammar(
            'start',
            [
                'start' => new ProductionRule('start', [
                    new Production([]),
                ]),
                'inner' => new ProductionRule('inner', [
                    new Production([
                        new Terminal('A'),
                        new Terminal('B'),
                        new Terminal('C'),
                    ]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        $production = new Production([
            new NonTerminal('inner'),
        ]);

        self::assertSame(3, $analyzer->estimateProductionLength($production));
    }

    public function testEstimateProductionLengthForEmptyProduction(): void
    {
        $grammar = new Grammar('start', []);
        $analyzer = new TerminationAnalyzer($grammar);

        $production = new Production([]);

        self::assertSame(0, $analyzer->estimateProductionLength($production));
    }

    public function testEstimateProductionLengthForMixedSymbols(): void
    {
        $grammar = new Grammar(
            'start',
            [
                'start' => new ProductionRule('start', [
                    new Production([]),
                ]),
                'expr' => new ProductionRule('expr', [
                    new Production([
                        new Terminal('NUM'),
                    ]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        $production = new Production([
            new Terminal('('),
            new NonTerminal('expr'),
            new Terminal(')'),
        ]);

        // '(' = 1, expr = 1, ')' = 1
        self::assertSame(3, $analyzer->estimateProductionLength($production));
    }
}
