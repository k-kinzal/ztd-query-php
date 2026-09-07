<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Derivation\Derivation;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\PlanBuilder;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\Derivation\TerminationAnalyzer;
use SqlFaker\Grammar\GenerationException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;
use Tests\Fixtures\SqlFaker\CoverageFixture;

#[CoversClass(PlanBuilder::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationCost::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationNode::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(GenerationException::class)]
#[UsesClass(LexicalException::class)]
final class PlanBuilderTest extends TestCase
{
    public function testRootResolvesExplicitReleaseAliasesAndPreservesTheUnspecifiedEntry(): void
    {
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('supports')->willReturn(true);
        $builder = new PlanBuilder(
            CoverageFixture::syntaxGrammar(),
            $lexical,
            startSymbol: static fn (?string $requested): string => 'expr'
        );
        self::assertSame('stmt', $builder->root(GenerationPlan::all()));
        self::assertSame('expr', $builder->root(GenerationPlan::fromRule('expression-alias')));
    }

    public function testMinimumExpansionsRespectsTheRootAndExplicitNonEmptyRequirement(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([]), new Production([new NonTerminal('leaf')])]),
            'leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])]),
        ]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('supports')->willReturn(true);
        $builder = new PlanBuilder($grammar, $lexical);
        self::assertSame(1, $builder->minimumExpansions(GenerationPlan::all()));
        self::assertSame(2, $builder->minimumExpansions(GenerationPlan::all()->requiringNonEmpty()));
        self::assertSame(1, $builder->minimumExpansions(GenerationPlan::fromRule('leaf')->requiringNonEmpty()));
    }

    public function testBuildResolvesNormalizedLexemesAndKeepsCallerSpecifiedTrivia(): void
    {
        $grammar = new Grammar('root', ['root' => new ProductionRule('root', [new Production([new Terminal('T')])])]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('supports')->willReturn(true);
        $lexical->method('spellings')->willReturnCallback(static fn (string $terminal): array => $terminal === '@TRIVIA' ? [' ', '/*x*/'] : ['first', 'second']);
        $builder = new PlanBuilder($grammar, $lexical, static fn (array $tokens): array => [...$tokens, 'ADDED']);
        $constraints = GenerationPlan::all()->withLexemes(['T' => ['explicit']])->withTrivia(['/*required*/'], ['/*optional*/']);
        $plan = $builder->build($constraints, 1, static fn (int $count): int => 0, static fn (int $count): int => $count - 1);
        self::assertSame('explicit', $plan->lexemeAt('T', 0));
        self::assertSame('second', $plan->lexemeAt('ADDED', 0));
        self::assertSame('/*required*/', $plan->triviaAt(0, false));
        self::assertSame('/*optional*/', $plan->triviaAt(0, true));
        self::assertNull($plan->startRule());
    }

    public function testBuildMakesAllSeparatorChoicesBeforeGeneration(): void
    {
        $grammar = new Grammar('root', ['root' => new ProductionRule('root', [new Production([new Terminal('T')])])]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('supports')->willReturn(true);
        $lexical->method('spellings')->willReturnCallback(static fn (string $terminal): array => $terminal === '@TRIVIA' ? [' ', '/*x*/'] : ['name']);
        $plan = (new PlanBuilder($grammar, $lexical))->build(
            GenerationPlan::all(),
            1,
            static fn (int $count): int => 0,
            static fn (int $count): int => $count - 1
        );
        self::assertSame('/*x*/', $plan->triviaAt(0, false));
        self::assertSame('/*x*/', $plan->triviaAt(0, true));
    }

    public function testDeriveReservesEverySiblingAndReturnsExplicitOccurrencePatterns(): void
    {
        $grammar = CoverageFixture::syntaxGrammar();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('supports')->willReturn(true);
        $builder = new PlanBuilder($grammar, $lexical);
        $constraints = GenerationPlan::constrained('stmt', ['stmt' => [ProductionPattern::at(0)]])->requiringNonEmpty();
        [$patterns, $terminals] = $builder->derive($constraints, 3, static fn (int $count): ?int => null);
        self::assertSame(['SELECT', '1'], array_map(static fn (Terminal $t): string => $t->value, $terminals));
        self::assertEquals(ProductionPattern::at(0), $patterns['tail'][0]);
        $plan = GenerationPlan::constrained('stmt', $patterns)->withExpansionBudget(3);
        $derivation = new Derivation($grammar, Factory::create(), new TerminationAnalyzer($grammar));
        self::assertEquals($terminals, $derivation->of('stmt', $plan));
    }

    public function testDeriveReportsUnknownRequestedRules(): void
    {
        $lexical = $this->createMock(LexicalGrammar::class);
        $builder = new PlanBuilder(CoverageFixture::syntaxGrammar(), $lexical);
        $this->expectException(GenerationException::class);
        $builder->derive(GenerationPlan::fromRule('missing'), 10, static fn (int $count): ?int => null);
    }

    public function testDeriveReportsExhaustedBudget(): void
    {
        $lexical = $this->createMock(LexicalGrammar::class);
        $builder = new PlanBuilder(CoverageFixture::syntaxGrammar(), $lexical);
        $this->expectException(GenerationException::class);
        $builder->derive(GenerationPlan::all(), 0, static fn (int $count): ?int => null);
    }

    public function testSelectUsesTheMinimumFiniteCompletionWhenNoChoiceIsSupplied(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('leaf')]), new Production([new Terminal('A')]), new Production([new Terminal('B')])]),
            'leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])]),
        ]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('supports')->willReturn(true);
        $builder = new PlanBuilder($grammar, $lexical);
        self::assertSame($grammar->ruleMap['root']->alternatives[1], $builder->select($grammar->ruleMap['root']->alternatives, [], true, 5, static fn (int $count): ?int => null));
        self::assertSame($grammar->ruleMap['root']->alternatives[2], $builder->select($grammar->ruleMap['root']->alternatives, [], true, 5, static fn (int $count): int => 2));
    }

    public function testSelectReportsConstraintsWithNoFiniteCompletion(): void
    {
        $lexical = $this->createMock(LexicalGrammar::class);
        $builder = new PlanBuilder(CoverageFixture::syntaxGrammar(), $lexical);
        $this->expectException(GenerationException::class);
        $builder->select([], [], false, 1, static fn (int $count): ?int => null);
    }

    public function testSpellingKeepsExplicitEmptyWitnesses(): void
    {
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('spellings')->willReturn(['', 'name']);
        $builder = new PlanBuilder(CoverageFixture::syntaxGrammar(), $lexical);
        self::assertSame('', $builder->spelling('T', static fn (int $count): ?int => null));
        self::assertSame('name', $builder->spelling('T', static fn (int $count): int => 1));
    }

    public function testSpellingReportsMissingWitnesses(): void
    {
        $lexical = $this->createMock(LexicalGrammar::class);
        $builder = new PlanBuilder(CoverageFixture::syntaxGrammar(), $lexical);
        $this->expectException(LexicalException::class);
        $builder->spelling('T', static fn (int $count): ?int => null);
    }
}
