<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\GenerationPlan;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionPattern;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use SqlFaker\Grammar\TerminationAnalyzer;
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
}
