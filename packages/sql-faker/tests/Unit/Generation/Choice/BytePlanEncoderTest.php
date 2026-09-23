<?php

declare(strict_types=1);

namespace Tests\Unit\Generation\Choice;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Candidate\ChoiceLexemeGenerator;
use SqlFaker\Generation\Candidate\ValueLexemeGenerator;
use SqlFaker\Generation\Choice\ByteChoices;
use SqlFaker\Generation\Choice\BytePlanCompiler;
use SqlFaker\Generation\Choice\BytePlanEncoder;
use SqlFaker\Generation\Choice\PlanBuilder;
use SqlFaker\Generation\Derivation\Completion\CompletionWitness;
use SqlFaker\Generation\Derivation\Completion\PatternProductions;
use SqlFaker\Generation\Derivation\CompletionCosts;
use SqlFaker\Generation\Derivation\CompletionFrontier;
use SqlFaker\Generation\Derivation\CompletionMemo;
use SqlFaker\Generation\Derivation\CompletionReduction;
use SqlFaker\Generation\Derivation\CompletionState;
use SqlFaker\Generation\Derivation\ConstrainedCompletion;
use SqlFaker\Generation\Derivation\ConstraintDependencies;
use SqlFaker\Generation\Derivation\Derivation;
use SqlFaker\Generation\Derivation\DerivationTrace;
use SqlFaker\Generation\Derivation\TerminationAnalyzer;
use SqlFaker\Generation\Derivation\TerminationCost;
use SqlFaker\Generation\Derivation\TokenGenerator;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;
use SqlFaker\Generation\Lexeme\LexicalGrammar;
use SqlFaker\Generation\Lexeme\OutputPart;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Output\BoundaryCompletion;
use SqlFaker\Generation\Output\CandidateResolver;
use SqlFaker\Generation\Output\CombinedSpacingRule;
use SqlFaker\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Plan\ProductionPattern;
use SqlFaker\Generation\Token\ProductionOccurrence;
use SqlFaker\Generation\Token\TerminalOccurrence;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\Generation\Value\CharacterDomain;
use SqlFaker\Generation\Value\ValueChoices;
use SqlFaker\Grammar\Model\Grammar;
use SqlFaker\Grammar\Model\NonTerminal;
use SqlFaker\Grammar\Model\Production;
use SqlFaker\Grammar\Model\ProductionRule;
use SqlFaker\Grammar\Model\Terminal;

#[CoversClass(BytePlanEncoder::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ProductionPattern::class)]
#[UsesClass(PlanBuilder::class)]
#[UsesClass(ByteChoices::class)]
#[UsesClass(BytePlanCompiler::class)]
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
final class BytePlanEncoderTest extends TestCase
{
    public function testEncodeWritesBytesTheCompilerDecodesToTheRecordedChoices(): void
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
        $input = (new BytePlanEncoder())->encode(
            $builder,
            null,
            3,
            static fn (int $count, array $candidates): int => $count - 1,
            static fn (int $count): int => 1,
        );
        $plan = (new BytePlanCompiler())->compile($input, $builder);
        self::assertSame(pack('V', 0), substr($input, 0, 4));
        self::assertSame(3, $plan->expansionBudget());
        self::assertEquals(ProductionPattern::at(1), $plan->patternAt('choice', 0));
        self::assertEquals(ProductionPattern::at(1), $plan->patternAt('choice', 1));
        self::assertSame('second', $plan->lexemeAt('U', 0));
        self::assertSame('second', $plan->lexemeAt('U', 1));
    }

    public function testEncodeMakesOpenLexicalChoicesTheWayTheCompilerReadsThePadding(): void
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
        $constraints = GenerationPlan::fromRule('root')->requiringNonEmpty();
        $input = (new BytePlanEncoder())->encode($builder, $constraints, 3, static fn (int $count, array $candidates): int => 0);
        $plan = (new BytePlanCompiler())->compile($input, $builder, $constraints);
        self::assertSame("\x00\x00\x00\x00\x00\x00\x00\x00\x00", $input);
        self::assertEquals(ProductionPattern::at(0), $plan->patternAt('choice', 0));
        self::assertSame('first', $plan->lexemeAt('T', 0));
        self::assertSame('first', $plan->lexemeAt('T', 1));
        self::assertTrue($plan->requiresNonEmpty());
    }

    public function testEncodeStopsRecordingLexicalChoicesAtTheFirstNullAnswer(): void
    {
        $grammar = new Grammar(
            'root',
            [
                'root' => new ProductionRule('root', [new Production([new NonTerminal('choice'), new NonTerminal('choice'), new NonTerminal('choice')])]),
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
        $answers = [1, null, 1];
        $input = (new BytePlanEncoder())->encode(
            $builder,
            null,
            4,
            static fn (int $count, array $candidates): int => 0,
            static function (int $count) use (&$answers): ?int {
                return array_shift($answers);
            },
        );
        $plan = (new BytePlanCompiler())->compile($input, $builder);
        self::assertSame("\x00\x00\x00\x00\x00\x01\x00\x00\x00\x00\x00", $input);
        self::assertSame(['first', 'first', 'second'], [$plan->lexemeAt('T', 0), $plan->lexemeAt('T', 1), $plan->lexemeAt('T', 2)]);
    }

    public function testEncodeWritesTheHeaderRelativeToTheConstraintsMinimumAndDefaultMaximum(): void
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
                new ValueLexemeGenerator('T', $literalDomain, ['first'], 'fixture', 'fixture-literal'),
                new ValueLexemeGenerator('U', $literalDomain, ['first'], 'fixture', 'fixture-literal'),
            ),
            new CandidateResolver(new CombinedSpacingRule()),
            'fixture',
        );
        $lexical->method('resolveSequence')->willReturnCallback(
            static fn (TerminalSequence $sequence, ?GenerationPlan $plan, Closure $choose) => $lexemePipeline->generate($sequence, $plan, $choose),
        );
        $builder = new PlanBuilder($grammar, $lexical);
        $choice = GenerationPlan::fromRule('choice');
        $largest = (new BytePlanEncoder())->encode($builder, null, BytePlanCompiler::MAXIMUM_BUDGET, static fn (int $count, array $candidates): int => 0);
        $single = (new BytePlanEncoder())->encode($builder, $choice, 1, static fn (int $count, array $candidates): int => 1);
        self::assertSame(pack('V', BytePlanCompiler::MAXIMUM_BUDGET - 3), substr($largest, 0, 4));
        self::assertSame(BytePlanCompiler::MAXIMUM_BUDGET, (new BytePlanCompiler())->compile($largest, $builder)->expansionBudget());
        self::assertSame("\x00\x00\x00\x00\x01", $single);
        self::assertEquals(ProductionPattern::at(1), (new BytePlanCompiler())->compile($single, $builder, $choice)->patternAt('choice', 0));
    }

    public function testInterleavePairsProductionBytesWithLexicalBytesAndPadsTheShorterStream(): void
    {
        self::assertSame('', BytePlanEncoder::interleave('', ''));
        self::assertSame("\x01\x00\x02\x00\x03", BytePlanEncoder::interleave("\x01\x02\x03", ''));
        self::assertSame("\x01\x05\x02\x06", BytePlanEncoder::interleave("\x01\x02", "\x05\x06"));
        self::assertSame("\x01\x05\x00\x06", BytePlanEncoder::interleave("\x01", "\x05\x06"));
    }
}
