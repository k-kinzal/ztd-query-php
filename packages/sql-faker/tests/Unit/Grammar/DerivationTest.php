<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionPattern;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;
use SqlFaker\Grammar\TerminationCost;

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

    public function testOfReportsASymbolTheGrammarDeclaresNoRuleFor(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [new Production([new NonTerminal('missing')])]),
        ]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer(
            $grammar,
            static fn (string $terminal): bool => true,
        ));

        $this->expectException(GenerationException::class);

        $derivation->of('stmt', GenerationPlan::all());
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

    public function testAffordableRejectsARemainderThatExceedsTheWholeBudget(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('T')])])]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer($grammar));
        $this->expectException(GenerationException::class);

        $derivation->affordable([new Production([])], new Production(array_fill(0, 5001, new NonTerminal('stmt'))));
    }

    public function testAffordableRejectsAnAlternativeThatCannotFitAfterTheRemainder(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('T')])])]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer($grammar));
        $this->expectException(GenerationException::class);

        $derivation->affordable(
            [new Production(array_fill(0, 5000, new NonTerminal('stmt')))],
            new Production([new NonTerminal('stmt')]),
        );
    }

    public function testAffordableAcceptsAnAlternativeThatExactlyUsesTheBudget(): void
    {
        $grammar = new Grammar('stmt', ['stmt' => new ProductionRule('stmt', [new Production([new Terminal('T')])])]);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer($grammar));
        $production = new Production(array_fill(0, 4999, new NonTerminal('stmt')));

        self::assertSame([$production], $derivation->affordable([$production], new Production([new NonTerminal('stmt')])));
    }

    public function testOfSelectsTerminationPolicyFromThePlan(): void
    {
        $grammar = new Grammar('stmt', [
            'stmt' => new ProductionRule('stmt', [
                new Production([new NonTerminal('short')]),
                new Production([new Terminal('A'), new Terminal('B')]),
            ]),
            'short' => new ProductionRule('short', [new Production([new Terminal('T')])]),
        ]);
        $faker = Factory::create();
        $analyzer = new TerminationAnalyzer($grammar);
        $plan = GenerationPlan::all()->withMaxDepth(1);

        self::assertEquals([new Terminal('T')], (new Derivation($grammar, $faker, $analyzer))->of('stmt', $plan));
        self::assertEquals(
            [new Terminal('A'), new Terminal('B')],
            (new Derivation($grammar, $faker, $analyzer))->of('stmt', $plan->withStepBudget()),
        );
    }
}
