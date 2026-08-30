<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionPattern;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;
use SqlFaker\Grammar\Walk\GenerationException;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\Grammar\Walk\TerminationAnalyzer;
use SqlFaker\Grammar\Walk\TerminationCost;
use SqlFaker\Grammar\Walk\ViableAlternatives;
use SqlFaker\Sqlite\Derivation;

#[CoversClass(Derivation::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(TerminationCost::class)]
#[UsesClass(ViableAlternatives::class)]
final class DerivationTest extends TestCase
{
    public function testOfRewritesTheStartSymbolUntilOnlyTerminalsAreLeft(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT'), new NonTerminal('nm')])]),
            'nm' => new ProductionRule('nm', [new Production([new Terminal('IDENT')])]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertEquals(
            [new Terminal('SELECT'), new Terminal('IDENT')],
            $derivation->of('stmt', GenerationPlan::all()),
        );
    }

    public function testOfReadsASymbolLemonDeclaresNoRuleForAsAToken(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new NonTerminal('STRICT')])]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertEquals([new Terminal('STRICT')], $derivation->of('stmt', GenerationPlan::all()));
    }

    public function testAffordableKeepsAnAlternativeThatStillLeavesRoomToFinish(): void
    {
        $short = new Production([new Terminal('A')]);
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [$short])]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertSame([$short], $derivation->affordable([$short], new Production([])));
    }

    public function testOfReportsAPlanNoAlternativeCanSatisfy(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('SELECT')])]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        $this->expectException(GenerationException::class);

        $derivation->of('stmt', GenerationPlan::constrained('stmt', [
            'stmt' => [ProductionPattern::containing('DELETE')],
        ]));
    }

    public function testFirstNonTerminalAnswersTheLeftmostPositionTheWalkActsOn(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([])])]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertSame(1, $derivation->firstNonTerminal([new Terminal('SELECT'), new NonTerminal('nm')]));
    }

    public function testFirstNonTerminalAnswersNothingWhenOnlyTerminalsAreLeft(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([])])]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertNull($derivation->firstNonTerminal([new Terminal('SELECT')]));
    }

    public function testSelectProductionTakesTheShortestAlternativeOncePastThePlanDepth(): void
    {
        $short = new Production([new Terminal('A')]);
        $long = new Production([new Terminal('A'), new Terminal('B'), new Terminal('C')]);
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [$long, $short])]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertEquals(
            [new Terminal('A')],
            $derivation->of('stmt', GenerationPlan::all()->withMaxDepth(1)),
        );
    }

    public function testSelectProductionChoosesFreelyWhileThePlanStillAllowsDepth(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('A')])]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertEquals([new Terminal('A')], $derivation->of('stmt', GenerationPlan::all()));
    }

    public function testRewrittenReplacesOnlyTheSymbolTheStepActedOn(): void
    {
        $derivation = new Derivation(new Grammar('start', []), Factory::create(), new TerminationAnalyzer(new Grammar('start', [])));

        $form = $derivation->rewritten(
            [new Terminal('a'), new NonTerminal('b'), new Terminal('c')],
            1,
            [new Terminal('x'), new Terminal('y')],
        );

        self::assertEquals(
            [new Terminal('a'), new Terminal('x'), new Terminal('y'), new Terminal('c')],
            $form,
        );
    }

    public function testOfReadsEverySymbolNoRuleIsDeclaredForAsAToken(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('FIRST'), new NonTerminal('SECOND')]),
            ]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertEquals(
            [new Terminal('FIRST'), new Terminal('SECOND')],
            $derivation->of('stmt', GenerationPlan::all()),
        );
    }

    public function testOfKeepsWhatSurroundsTheSymbolItRewrites(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('BEFORE'), new NonTerminal('mid'), new Terminal('AFTER')]),
            ]),
            'mid' => new ProductionRule('mid', [
                new Production([new Terminal('ONE'), new Terminal('TWO')]),
            ]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertEquals(
            [new Terminal('BEFORE'), new Terminal('ONE'), new Terminal('TWO'), new Terminal('AFTER')],
            $derivation->of('stmt', GenerationPlan::all()),
        );
    }

    public function testFirstNonTerminalAnswersWhereTheWalkActsNext(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('A')])]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertSame(1, $derivation->firstNonTerminal([new Terminal('A'), new NonTerminal('b'), new NonTerminal('c')]));
        self::assertNull($derivation->firstNonTerminal([new Terminal('A'), new Terminal('B')]));
    }

    public function testAffordableDropsAnAlternativeTheRemainingBudgetCannotHold(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new Terminal('A')])]),
            'deep' => new ProductionRule('deep', [new Production([new NonTerminal('deep'), new Terminal('B')])]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        $cheap = new Production([new Terminal('A')]);

        self::assertSame([$cheap], $derivation->affordable([$cheap], new Production([])));
    }

    public function testAffordableRefusesWhenNothingFitsInWhatIsLeftOfTheBudget(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new NonTerminal('stmt')])]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        $this->expectException(GenerationException::class);

        $derivation->affordable(
            [new Production([new Terminal('A')])],
            new Production(array_fill(0, 6000, new NonTerminal('stmt'))),
        );
    }
    public function testOfTakesTheAlternativeThatFinishesSoonestOnceThePlanIsDeepEnough(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new Terminal('A'), new NonTerminal('stmt')]),
                new Production([new Terminal('B')]),
            ]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        self::assertEquals(
            [new Terminal('B')],
            $derivation->of('stmt', GenerationPlan::all()->withMaxDepth(1)),
        );
    }
}
