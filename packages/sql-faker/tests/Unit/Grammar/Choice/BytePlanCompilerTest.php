<?php

declare (strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Choice;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Choice\ByteChoices;
use SqlFaker\Grammar\Choice\BytePlanCompiler;
use SqlFaker\Grammar\Derivation\Completion\CompletionWitness;
use SqlFaker\Grammar\Derivation\Completion\PatternProductions;
use SqlFaker\Grammar\Derivation\CompletionCosts;
use SqlFaker\Grammar\Derivation\CompletionFrontier;
use SqlFaker\Grammar\Derivation\CompletionMemo;
use SqlFaker\Grammar\Derivation\CompletionReduction;
use SqlFaker\Grammar\Derivation\CompletionState;
use SqlFaker\Grammar\Derivation\ConstrainedCompletion;
use SqlFaker\Grammar\Derivation\ConstraintDependencies;
use SqlFaker\Grammar\Derivation\Derivation;
use SqlFaker\Grammar\Derivation\DerivationTrace;
use SqlFaker\Grammar\Derivation\GenerationPlan;
use SqlFaker\Grammar\Derivation\PlanBuilder;
use SqlFaker\Grammar\Derivation\ProductionPattern;
use SqlFaker\Grammar\Derivation\TerminationAnalyzer;
use SqlFaker\Grammar\Derivation\TerminationCost;
use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\BoundaryCompletion;
use SqlFaker\Grammar\Generation\Output\CandidateResolver;
use SqlFaker\Grammar\Generation\Output\OutputPart;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Grammar\Generation\Token\TokenGenerator;
use SqlFaker\Grammar\Generation\Value\CharacterDomain;
use SqlFaker\Grammar\Generation\Value\ValueChoices;
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
#[UsesClass(ByteChoices::class)]
#[UsesClass(CompletionCosts::class)]
#[UsesClass(Grammar::class)]
#[UsesClass(NonTerminal::class)]
#[UsesClass(Production::class)]
#[UsesClass(ProductionRule::class)]
#[UsesClass(Terminal::class)]
#[UsesClass(Derivation::class)]
#[UsesClass(DerivationTrace::class)]
#[UsesClass(TerminationAnalyzer::class)]
#[UsesClass(TerminationCost::class)]
#[UsesClass(ChoiceLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(ValueLexemeGenerator::class)]
#[UsesClass(CandidateResolver::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(ReverseLexemeGenerator::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(TokenGenerator::class)]
#[UsesClass(CompletionState::class)]
#[UsesClass(CompletionFrontier::class)]
#[UsesClass(ConstrainedCompletion::class)]
#[UsesClass(ValueChoices::class)]
#[UsesClass(BoundaryCompletion::class)]
#[UsesClass(CompletionMemo::class)]
#[UsesClass(CompletionReduction::class)]
#[UsesClass(ConstraintDependencies::class)]
#[UsesClass(PatternProductions::class)]
#[UsesClass(CompletionWitness::class)]
#[UsesClass(CharacterDomain::class)]
final class BytePlanCompilerTest extends TestCase
{
    /**
     * @param GenerationPlan<bool>|null $constraints
     */
    #[DataProvider('providerInputBudgets')]

    public function testCompileMapsTheHeaderIntoTheAllowedExpansionRange(string $input, ?GenerationPlan $constraints, int $expected): void
    {
        $grammar = new Grammar(
            'root',
            [
                'root' => new ProductionRule('root', [new Production([new NonTerminal('leaf')])]),
                'leaf' => new ProductionRule('leaf', [new Production([new Terminal('T')])]),
            ],
        );
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('isNonOutput')->willReturn(false);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('T', $literalDomain, ['T'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('U', $literalDomain, ['U'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
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
        yield 'second header byte' => ["\x00\x01", null, 258];
        yield 'third header byte' => ["\x00\x00\x01", null, 551];
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

    public function testCompileSeparatesProductionChoicesFromLexemesAndCompletesAnOddInput(): void
    {
        $grammar = new Grammar(
            'root',
            [
                'root' => new ProductionRule('root', [new Production([new NonTerminal('choice'), new NonTerminal('choice')])]),
                'choice' => new ProductionRule('choice', [new Production([new Terminal('T')]), new Production([new Terminal('U')])]),
            ],
        );
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('isNonOutput')->willReturn(false);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('T', $literalDomain, ['first', 'second'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('U', $literalDomain, ['first', 'second'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $builder = new PlanBuilder($grammar, $lexical);
        $plan = (new BytePlanCompiler())->compile("\x00\x00\x00\x00\x00\x01\x01\x00\x00", $builder);
        self::assertEquals(ProductionPattern::at(1), $plan->patternAt('choice', 0));
        self::assertEquals(ProductionPattern::at(0), $plan->patternAt('choice', 1));
        self::assertSame('first', $plan->lexemeAt('U', 0));
        self::assertSame('second', $plan->lexemeAt('T', 0));
        self::assertNotNull($plan->candidateKeyAt('U', 0));
    }

    /**
     * @return list<array{string}>
     */

    public static function providerInputs(): array
    {
        return [[''], ["\x01"], ["\xff\xff\xff\xff"], ["\x00\x00\x00\x00abc"], [str_repeat("\xff", 40)]];
    }
    #[DataProvider('providerInputs')]

    public function testCompileResolvesChoicesIntoInspectableReusableInstructions(string $input): void
    {
        $grammar = (new Grammar(
            'stmt',
            [
                'stmt' => new ProductionRule(
                    'stmt',
                    [
                        new Production([new Terminal('SELECT'), new NonTerminal('expr'), new NonTerminal('tail')]),
                        new Production([new Terminal('DELETE'), new Terminal('FROM'), new Terminal('missing')]),
                    ],
                ),
                'expr' => new ProductionRule(
                    'expr',
                    [
                        new Production([new Terminal('1')]),
                        new Production([new NonTerminal('expr'), new Terminal('+'), new NonTerminal('expr')]),
                    ],
                ),
                'tail' => new ProductionRule('tail', [new Production([]), new Production([new Terminal('AS'), new Terminal('name')])]),
            ],
        ))->identified();
        $lexical = $this->createMock(LexicalGrammar::class);
        $lexical->method('isNonOutput')->willReturn(false);
        $literalDomain = new CharacterDomain(array_map(chr(...), range(0, 255)), 0, 255);
        $lexemePipeline = new ReverseLexemeGenerator(
            new ChoiceLexemeGenerator(
                new ValueLexemeGenerator('SELECT', $literalDomain, ['SELECT'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('DELETE', $literalDomain, ['DELETE'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('FROM', $literalDomain, ['FROM'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('missing', $literalDomain, ['missing'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('1', $literalDomain, ['1'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('+', $literalDomain, ['+'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('AS', $literalDomain, ['AS'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('name', $literalDomain, ['name'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('CHANGED', $literalDomain, ['CHANGED'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $builder = new PlanBuilder($grammar, $lexical);
        $plan = (new BytePlanCompiler())->compile($input, $builder);
        self::assertEquals($plan, (new BytePlanCompiler())->compile($input, $builder));
        self::assertNotNull($plan->patternAt('stmt', 0));
        self::assertNotNull($plan->candidateKeyAt('SELECT', 0) ?? $plan->candidateKeyAt('DELETE', 0));
        self::assertFalse($plan->requiresNonEmpty());
        self::assertNull($plan->startRule());
    }
}
