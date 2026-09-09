<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Derivation;

use Closure;
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
#[UsesClass(\SqlFaker\Generation\SqlGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationTrace::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\SqlSerializer::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalMappingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenRewriter::class)]
final class PlanBuilderTest extends TestCase
{
    public function testRootResolvesOnlyAnExplicitReleaseAlias(): void
    {
        $builder = new PlanBuilder(CoverageFixture::syntaxGrammar(), $this->createMock(LexicalGrammar::class), startSymbol: static fn (?string $requested): string => 'expr');
        self::assertSame('stmt', $builder->root(GenerationPlan::all()));
        self::assertSame('expr', $builder->root(GenerationPlan::fromRule('alias')));
    }

    public function testMinimumExpansionsKeepsTheNonEmptyRequirementSeparateFromNullability(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([]), new Production([new NonTerminal('leaf')])]),
            'leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])]),
        ]);
        $builder = new PlanBuilder($grammar, $this->createMock(LexicalGrammar::class));
        self::assertSame(1, $builder->minimumExpansions(GenerationPlan::all()));
        self::assertSame(2, $builder->minimumExpansions(GenerationPlan::all()->requiringNonEmpty()));
    }

    public function testBuildUsesTheSameContextualRewriteAndPinsItsCompleteCandidate(): void
    {
        $grammar = new Grammar('root', ['root' => new ProductionRule('root', [new Production([new Terminal('T')])])]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('resolveSequence')->willReturnCallback(CoverageFixture::resolve(...));
        $rewriter = new \SqlFaker\Grammar\Generation\Token\TokenRewriter(new \SqlFaker\Grammar\Generation\Token\TerminalMappingRule('root', 'T', 'CONTEXTUAL', 'fixture'));
        $constraints = GenerationPlan::all()->withLexemes(['T' => ['explicit']]);
        $plan = (new PlanBuilder($grammar, $lexical, $rewriter))->build($constraints, 1, static fn (int $count): int => 0, static fn (int $count): int => $count - 1);
        self::assertSame('explicit', $plan->lexemeAt('CONTEXTUAL', 0));
        self::assertNotNull($plan->candidateKeyAt('CONTEXTUAL', 0));
        self::assertNull($plan->startRule());
        $generator = new \SqlFaker\Generation\SqlGenerator($grammar, Factory::create(), $lexical, $rewriter);
        self::assertSame('explicit', $generator->generate($plan));
        self::assertSame('explicit', $generator->generate($plan));
    }

    public function testBuildPreservesRepeatedProductionAndLexemeConstraints(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('leaf'), new NonTerminal('leaf'), new NonTerminal('leaf')])]),
            'leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')]), new Production([new Terminal('U')])]),
        ]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('resolveSequence')->willReturnCallback(CoverageFixture::resolve(...));
        $constraints = GenerationPlan::constrained('root', ['leaf' => [ProductionPattern::at(0), ProductionPattern::at(0), ProductionPattern::at(1)]])
            ->withLexemes(['T' => ['first', 'second'], 'U' => ['third']]);
        $plan = (new PlanBuilder($grammar, $lexical))->build($constraints, 4, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
        self::assertEquals(ProductionPattern::at(0), $plan->patternAt('leaf', 0));
        self::assertEquals(ProductionPattern::at(0), $plan->patternAt('leaf', 1));
        self::assertEquals(ProductionPattern::at(1), $plan->patternAt('leaf', 2));
        self::assertSame('first', $plan->lexemeAt('T', 0));
        self::assertSame('second', $plan->lexemeAt('T', 1));
        self::assertSame('third', $plan->lexemeAt('U', 0));
    }

    public function testBuildPreservesEmptyMarkerCandidatesWithoutLosingNonEmptyCompletion(): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new Terminal('END'), new NonTerminal('leaf')])]),
            'leaf' => new ProductionRule('leaf', [new Production([]), new Production([new Terminal('T')])]),
        ]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('isNonOutput')->willReturnCallback(static fn (string $name): bool => $name === 'END');
        $lexical->method('resolveSequence')->willReturnCallback(static fn (\SqlFaker\Grammar\Generation\Token\TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => CoverageFixture::resolve($sequence, $plan, $choose, ['END' => ['']]));
        $builder = new PlanBuilder($grammar, $lexical);
        $empty = $builder->build(GenerationPlan::all(), 2, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
        $nonEmpty = $builder->build(GenerationPlan::all()->requiringNonEmpty(), 2, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
        self::assertSame('', $empty->lexemeAt('END', 0));
        self::assertNotNull($empty->candidateKeyAt('END', 0));
        self::assertNull($empty->lexemeAt('T', 0));
        self::assertSame('T', $nonEmpty->lexemeAt('T', 0));
    }

    public function testBuildReservesAllPendingSiblingsAndRetainsTheOriginalOrdinal(): void
    {
        $grammar = CoverageFixture::syntaxGrammar();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('resolveSequence')->willReturnCallback(CoverageFixture::resolve(...));
        $constraints = GenerationPlan::constrained('stmt', ['stmt' => [ProductionPattern::at(0)]])->requiringNonEmpty();
        $plan = (new PlanBuilder($grammar, $lexical))->build($constraints, 3, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
        self::assertEquals(ProductionPattern::at(0), $plan->patternAt('tail', 0));
        self::assertSame('SELECT 1', (new \SqlFaker\Generation\SqlGenerator($grammar, Factory::create(), $lexical))->generate($plan));
    }

    public function testBuildPropagatesMissingCandidateFailuresWithoutRetrying(): void
    {
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->expects(self::once())->method('resolveSequence')->willThrowException(new LexicalException('missing candidate'));
        $builder = new PlanBuilder(CoverageFixture::syntaxGrammar(), $lexical);
        $this->expectException(LexicalException::class);
        $builder->build(GenerationPlan::all(), 5, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
    }

    public function testBuildReportsAnUnknownRequestedRule(): void
    {
        $builder = new PlanBuilder(CoverageFixture::syntaxGrammar(), $this->createMock(LexicalGrammar::class));
        $this->expectException(GenerationException::class);
        $builder->build(GenerationPlan::fromRule('missing'), 5, static fn (int $count): ?int => null, static fn (int $count): ?int => null);
    }
}
