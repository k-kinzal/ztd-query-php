<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Walk;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Grammar\Walk\TerminationAnalyzer;
use SqlFaker\Grammar\Walk\TerminationCost;

#[CoversClass(TerminationAnalyzer::class)]
#[UsesClass(TerminationCost::class)]
#[CoversClass(Grammar::class)]
#[CoversClass(NonTerminal::class)]
#[CoversClass(Production::class)]
#[CoversClass(ProductionRule::class)]
#[CoversClass(Terminal::class)]
final class TerminationAnalyzerTest extends TestCase
{
    public function testGetMinLengthTerminalOnly(): void
    {
        $grammar = new Grammar(
            'start',
            [
                'start' => new ProductionRule('start', [
                    new Production([new Terminal('TOKEN')]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        self::assertSame(1, $analyzer->getMinLength('start'));
    }

    public function testGetMinLengthEmptyProduction(): void
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
                    new Production([new Terminal('A'), new Terminal('B'), new Terminal('C')]),
                    new Production([new Terminal('X')]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        self::assertSame(1, $analyzer->getMinLength('expr'));
    }

    public function testGetMinLengthNestedRules(): void
    {
        $grammar = new Grammar(
            'a',
            [
                'a' => new ProductionRule('a', [
                    new Production([new NonTerminal('b')]),
                ]),
                'b' => new ProductionRule('b', [
                    new Production([new NonTerminal('c')]),
                ]),
                'c' => new ProductionRule('c', [
                    new Production([new Terminal('TOKEN')]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        self::assertSame(1, $analyzer->getMinLength('a'));
        self::assertSame(1, $analyzer->getMinLength('b'));
        self::assertSame(1, $analyzer->getMinLength('c'));
    }

    public function testEstimateProductionLengthTerminals(): void
    {
        $grammar = new Grammar('start', []);
        $analyzer = new TerminationAnalyzer($grammar);

        $production = new Production([new Terminal('A'), new Terminal('B')]);

        self::assertSame(2, $analyzer->estimateProductionLength($production));
    }

    public function testEstimateProductionLengthNonTerminals(): void
    {
        $grammar = new Grammar(
            'start',
            [
                'start' => new ProductionRule('start', [
                    new Production([]),
                ]),
                'inner' => new ProductionRule('inner', [
                    new Production([new Terminal('A'), new Terminal('B'), new Terminal('C')]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        $production = new Production([new NonTerminal('inner')]);

        self::assertSame(3, $analyzer->estimateProductionLength($production));
    }

    public function testEstimateProductionLengthEmpty(): void
    {
        $grammar = new Grammar('start', []);
        $analyzer = new TerminationAnalyzer($grammar);

        $production = new Production([]);

        self::assertSame(0, $analyzer->estimateProductionLength($production));
    }

    public function testEstimateProductionStepsBreaksARecursiveLengthTie(): void
    {
        $recursive = new Production([new NonTerminal('value')]);
        $terminal = new Production([new Terminal('VALUE')]);
        $grammar = new Grammar('value', [
            'value' => new ProductionRule('value', [$recursive, $terminal]),
        ]);
        $analyzer = new TerminationAnalyzer($grammar);

        self::assertSame(1, $analyzer->estimateProductionLength($recursive));
        self::assertSame(1, $analyzer->estimateProductionLength($terminal));
        self::assertSame(1, $analyzer->estimateProductionSteps($recursive));
        self::assertSame(0, $analyzer->estimateProductionSteps($terminal));
    }

    public function testTreatsUnknownSupportedNonTerminalAsOneToken(): void
    {
        $analyzer = new TerminationAnalyzer(new Grammar('start', []));
        $production = new Production([new NonTerminal('lexer_token')]);

        self::assertSame(1, $analyzer->getMinLength('lexer_token'));
        self::assertSame(1, $analyzer->estimateProductionLength($production));
        self::assertSame(1, $analyzer->estimateProductionSteps($production));
    }

    public function testEstimateProductionStepsRejectsUnsupportedTerminal(): void
    {
        $analyzer = new TerminationAnalyzer(
            new Grammar('start', []),
            static fn (string $terminal): bool => $terminal !== 'UNSUPPORTED',
        );

        self::assertSame(
            PHP_INT_MAX,
            $analyzer->estimateProductionSteps(new Production([new Terminal('UNSUPPORTED')])),
        );
    }

    public function testEstimateProductionLengthMixed(): void
    {
        $grammar = new Grammar(
            'start',
            [
                'start' => new ProductionRule('start', [
                    new Production([]),
                ]),
                'expr' => new ProductionRule('expr', [
                    new Production([new Terminal('NUM')]),
                ]),
            ]
        );

        $analyzer = new TerminationAnalyzer($grammar);

        $production = new Production([
            new Terminal('('),
            new NonTerminal('expr'),
            new Terminal(')'),
        ]);

        self::assertSame(3, $analyzer->estimateProductionLength($production));
    }

    public function testComputesAViewThatExcludesUnsupportedTerminals(): void
    {
        $unsupported = new Production([new Terminal('UNSUPPORTED')]);
        $supported = new Production([new Terminal('SUPPORTED')]);
        $grammar = new Grammar('start', [
            'start' => new ProductionRule('start', [$unsupported, $supported]),
        ]);
        $analyzer = new TerminationAnalyzer($grammar, static fn (string $terminal): bool => $terminal !== 'UNSUPPORTED');

        self::assertFalse($analyzer->isProductionViable($unsupported));
        self::assertTrue($analyzer->isProductionViable($supported));
        self::assertSame(1, $analyzer->getMinLength('start'));
    }

    public function testPropagatesLexicalImpossibilityThroughNonTerminals(): void
    {
        $grammar = new Grammar('start', [
            'start' => new ProductionRule('start', [new Production([new NonTerminal('nested')])]),
            'nested' => new ProductionRule('nested', [new Production([new Terminal('UNSUPPORTED')])]),
        ]);
        $analyzer = new TerminationAnalyzer($grammar, static fn (string $terminal): bool => $terminal !== 'UNSUPPORTED');

        self::assertSame(PHP_INT_MAX, $analyzer->getMinLength('start'));
    }

    public function testIsProductionViableAcceptsAProductionSomeDerivationFinishes(): void
    {
        $grammar = new Grammar('nm', ['nm' => new ProductionRule('nm', [new Production([new Terminal('IDENT')])])]);
        $analyzer = new TerminationAnalyzer($grammar);

        self::assertTrue($analyzer->isProductionViable(new Production([new NonTerminal('nm')])));
    }

    public function testIsProductionViableRejectsAProductionThatCanNeverBeFinished(): void
    {
        $grammar = new Grammar('loop', ['loop' => new ProductionRule('loop', [new Production([new NonTerminal('loop')])])]);
        $analyzer = new TerminationAnalyzer($grammar);

        self::assertFalse($analyzer->isProductionViable(new Production([new NonTerminal('loop')])));
    }
}
