<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Choice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Choice\BytePlanCompiler;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\PlanBuilder;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\LexicalGrammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Terminal;

#[CoversClass(BytePlanCompiler::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(PlanBuilder::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\ByteChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionCosts::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\Derivation::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\DerivationTrace::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationAnalyzer::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\TerminationCost::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeInput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TokenGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionState::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionFrontier::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstrainedCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionMemo::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\CompletionReduction::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ConstraintDependencies::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\PatternProductions::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\CompletionWitness::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
final class BytePlanCompilerTest extends TestCase
{
    /**
     * @param GenerationPlan<bool>|null $constraints
     */
    #[DataProvider('providerInputBudgets')]
    public function testCompileMapsTheHeaderIntoTheAllowedExpansionRange(string $input, ?GenerationPlan $constraints, int $expected): void
    {
        $grammar = new Grammar('root', [
            'root' => new ProductionRule('root', [new Production([new NonTerminal('leaf')])]),
            'leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])]),
        ]);
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('isNonOutput')->willReturn(false);
        $lexical->method('resolveSequence')->willReturnCallback(\Tests\Fixtures\SqlFaker\CoverageFixture::resolve(...));
        $plan = (new BytePlanCompiler())->compile($input, new PlanBuilder($grammar, $lexical), $constraints);

        self::assertSame($expected, $plan->expansionBudget());
        self::assertSame($constraints?->startRule(), $plan->startRule());
    }

    /**
     * @return iterable<string, array{string, GenerationPlan<bool>|null, int}>
     */
    public static function providerInputBudgets(): iterable
    {
        yield 'empty' => ['', null, 2];
        yield 'short header' => ["\x01", null, 3];
        yield 'second header byte' => ["\0\x01", null, 258];
        yield 'third header byte' => ["\0\0\x01", null, 551];
        yield 'all header bytes' => ["\x01\x02\x03\x04", null, 4450];
        yield 'body does not change budget' => ["\x01\x02\x03\x04\xff", null, 4450];
        yield 'default maximum' => [pack('V', 4998), null, 5000];
        yield 'default wraparound' => [pack('V', 4999), null, 2];
        yield 'unsigned header' => ["\xff\xff\xff\xff", null, 1462];
        yield 'explicit maximum' => [pack('V', 7), GenerationPlan::all()->withExpansionBudget(9), 9];
        yield 'explicit wraparound' => [pack('V', 8), GenerationPlan::all()->withExpansionBudget(9), 2];
        yield 'single possible budget' => ["\xff", GenerationPlan::fromRule('leaf')->withExpansionBudget(1), 1];
        yield 'largest allowed budget' => [pack('V', 999998), GenerationPlan::all()->withExpansionBudget(1000000), 1000000];
    }

}
