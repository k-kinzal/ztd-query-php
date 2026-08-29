<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Model\ProductionPattern;
use SqlFaker\Grammar\Walk\GenerationException;
use SqlFaker\Grammar\Walk\GenerationPlan;
use SqlFaker\MySql\Derivation;
use SqlFaker\MySql\Grammar\Grammar;
use SqlFaker\MySql\Grammar\NonTerminal;
use SqlFaker\MySql\Grammar\Production;
use SqlFaker\MySql\Grammar\ProductionRule;
use SqlFaker\MySql\Grammar\Terminal;
use SqlFaker\MySql\Grammar\TerminationAnalyzer;
use SqlFaker\MySql\Grammar\TerminationCost;
use SqlFaker\MySql\Grammar\ViableAlternatives;

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
}
