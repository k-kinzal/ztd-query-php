<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Generation\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Candidate\ChoiceLexemeGenerator;
use SqlFaker\Generation\Candidate\FixedLexemeGenerator;
use SqlFaker\Generation\Candidate\MatchingLexemeGenerator;
use SqlFaker\Generation\Candidate\SequenceLexemeGenerator;
use SqlFaker\Generation\Candidate\ValueLexemeGenerator;
use SqlFaker\Generation\Exception\LexicalException;
use SqlFaker\Generation\Lexeme\Lexeme;
use SqlFaker\Generation\Lexeme\LexemeBoundary;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\LexemeSequence;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Lexeme\SpacingConstraint;
use SqlFaker\Generation\Lexeme\SpacingRule;
use SqlFaker\Generation\Output\CandidateResolver;
use SqlFaker\Generation\Output\CombinedSpacingRule;
use SqlFaker\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Generation\Output\SqlSerializer;
use SqlFaker\Generation\Plan\GenerationPlan;
use SqlFaker\Generation\Token\TerminalSequence;

#[CoversClass(ReverseLexemeGenerator::class)]
#[UsesClass(GenerationPlan::class)]
#[UsesClass(ChoiceLexemeGenerator::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(MatchingLexemeGenerator::class)]
#[UsesClass(ValueLexemeGenerator::class)]
#[UsesClass(SequenceLexemeGenerator::class)]
#[UsesClass(CandidateResolver::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SqlSerializer::class)]
#[UsesClass(CombinedSpacingRule::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(\SqlFaker\Generation\Plan\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Generation\Choice\BytePlanCompiler::class)]
final class ReverseLexemeGeneratorTest extends TestCase
{
    public function testGenerateCompoundOutputRemainsInOrderAndEofAddsNoBoundary(): void
    {
        $lexemes = new ChoiceLexemeGenerator(
            new MatchingLexemeGenerator('WITH_ROLLUP_SYM', new SequenceLexemeGenerator(
                new FixedLexemeGenerator('WITH', 'keyword', 'with'),
                new FixedLexemeGenerator('ROLLUP', 'keyword', 'rollup'),
            )),
            new MatchingLexemeGenerator('EOF', new FixedLexemeGenerator('', 'marker', 'eof')),
        );
        $generator = new ReverseLexemeGenerator($lexemes, new CandidateResolver(new CombinedSpacingRule()), 'demo');
        $output = $generator->generate(TerminalSequence::fromNames(['WITH_ROLLUP_SYM', 'EOF']), null, static fn (int $count): int => $count - 1);
        self::assertSame('WITH ROLLUP', (new SqlSerializer())->serialize($output->pieces()));
        self::assertSame([0, 0], array_map(static fn ($part): int => $part->lexeme->origin->id, $output->parts));
    }

    public function testConflictingCandidateIsNotSelectedOrAllowedToAlterAnotherCandidate(): void
    {
        $lexemes = new ChoiceLexemeGenerator(
            new MatchingLexemeGenerator('NOW_SYM', new ChoiceLexemeGenerator(
                new FixedLexemeGenerator('NOW', 'function', 'function'),
                new FixedLexemeGenerator('CURRENT_TIMESTAMP', 'keyword', 'keyword'),
            )),
            new MatchingLexemeGenerator('(', new FixedLexemeGenerator('(', 'symbol', 'open')),
        );
        $spacing = $this->createMock(SpacingRule::class);
        $spacing->method('apply')->willReturnCallback(static fn (LexemeBoundary $boundary, LexemeInput $input): SpacingConstraint =>
            $boundary->left->kind === 'function' ? new SpacingConstraint(0, ['join', 'space']) : new SpacingConstraint());
        $generator = new ReverseLexemeGenerator($lexemes, new CandidateResolver($spacing), 'demo');
        $output = $generator->generate(TerminalSequence::fromNames(['NOW_SYM', '(']), null, static fn (int $count): int => 0);
        self::assertSame('CURRENT_TIMESTAMP (', (new SqlSerializer())->serialize($output->pieces()));
    }

    public function testMissingHandlerFailsAtTheActualTerminal(): void
    {
        $generator = new ReverseLexemeGenerator(new ChoiceLexemeGenerator(), new CandidateResolver(new CombinedSpacingRule()), 'demo');
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('UNKNOWN');
        $generator->generate(TerminalSequence::fromNames(['UNKNOWN']), null, static fn (int $count): int => 0);
    }
    public function testGenerateRetainsAPlannedSpellingWhenAContextualRewriteRenamesItsTerminal(): void
    {
        $original = TerminalSequence::fromNames(['IDENT']);
        $sequence = $original->replace(0, 1, [$original->terminals[0]->replaced('POLICY_MODE', 'policy')], 'policy');
        $generator = new ReverseLexemeGenerator(
            new ValueLexemeGenerator('POLICY_MODE', new \SqlFaker\Generation\Value\WordDomain(['PERMISSIVE', 'RESTRICTIVE'], true), ['PERMISSIVE'], 'identifier', 'policy-mode'),
            new CandidateResolver(new CombinedSpacingRule()),
            'test',
        );
        $plan = GenerationPlan::all()->withLexemes(['IDENT' => ['restrictive']]);
        $result = $generator->generate($sequence, $plan, static fn (int $count): int => 0);
        self::assertSame('restrictive', (new SqlSerializer())->serialize($result->pieces()));
        $overridden = $plan->withLexemes(['IDENT' => ['restrictive'], 'POLICY_MODE' => ['permissive']]);
        self::assertSame('permissive', (new SqlSerializer())->serialize($generator->generate($sequence, $overridden, static fn (int $count): int => 0)->pieces()));
    }

    public function testSelectRejectsAnOutOfRangeDecision(): void
    {
        $generator = new ReverseLexemeGenerator(new ChoiceLexemeGenerator(new FixedLexemeGenerator('A', 'keyword', 'a'), new FixedLexemeGenerator('B', 'keyword', 'b')), new CandidateResolver(new CombinedSpacingRule()), 'test');
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Candidate selector returned an out-of-range index for WORD');
        $generator->select(new LexemeInput(TerminalSequence::fromNames(['WORD']), 0, new ResolvedOutput()), static fn (int $count): int => $count);
    }

    public function testMatchesRequestComparesTheWholeCompoundSpelling(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['WORD']), 0, new ResolvedOutput(), 'WITH ROLLUP');
        $candidate = new LexemeSequence([new Lexeme('WITH', 'keyword', $input->terminal(), 'source'), new Lexeme('ROLLUP', 'keyword', $input->terminal(), 'source')], 'phrase');
        $generator = new ReverseLexemeGenerator(new ChoiceLexemeGenerator(), new CandidateResolver(new CombinedSpacingRule()), 'test');
        self::assertTrue($generator->matchesRequest($candidate, $input));
        self::assertFalse($generator->matchesRequest(new LexemeSequence([], 'empty'), $input));
    }

    public function testGenerateRespectsTheOrderOfRepeatedPlannedOccurrencesAfterRenaming(): void
    {
        $original = TerminalSequence::fromNames(['IDENT', 'IDENT', 'IDENT']);
        $sequence = $original->replace(0, 3, array_map(static fn ($terminal) => $terminal->replaced('NAME', 'context'), $original->terminals), 'context');
        $generator = new ReverseLexemeGenerator(new ValueLexemeGenerator('NAME', new \SqlFaker\Generation\Value\CharacterDomain(str_split('abcdefghijklmnopqrstuvwxyz'), 1, 64), ['fallback'], 'identifier', 'names'), new CandidateResolver(new CombinedSpacingRule()), 'test');
        $plan = GenerationPlan::all()->withLexemes(['IDENT' => ['first', 'second', 'third']]);
        self::assertSame('first second third', (new SqlSerializer())->serialize($generator->generate($sequence, $plan, static fn (int $count): int => 0)->pieces()));
        $overridden = $plan->withLexemes(['NAME' => ['fourth', 'fifth', 'sixth']]);
        self::assertSame('fourth fifth sixth', (new SqlSerializer())->serialize($generator->generate($sequence, $overridden, static fn (int $count): int => 0)->pieces()));
    }

    public function testSelectCanChooseTheLastOfSeveralCompatibleCandidates(): void
    {
        $generator = new ReverseLexemeGenerator(new ChoiceLexemeGenerator(
            new FixedLexemeGenerator('FIRST', 'keyword', 'first'),
            new FixedLexemeGenerator('MIDDLE', 'keyword', 'middle'),
            new FixedLexemeGenerator('LAST', 'keyword', 'last'),
        ), new CandidateResolver(new CombinedSpacingRule()), 'test');
        $output = $generator->generate(TerminalSequence::fromNames(['WORD']), null, static function (int $count): int {
            self::assertSame(3, $count);
            return 2;
        });
        self::assertSame('LAST', (new SqlSerializer())->serialize($output->pieces()));
    }

    public function testSelectReportsCandidateAndBoundarySourcesWhenAllCandidatesConflict(): void
    {
        $spacing = self::createStub(SpacingRule::class);
        $spacing->method('apply')->willReturn(new SpacingConstraint(0, ['require-join', 'require-space']));
        $generator = new ReverseLexemeGenerator(new FixedLexemeGenerator('WORD', 'keyword', 'word-definition'), new CandidateResolver($spacing), 'demo');
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('No compatible lexeme for WORD at 0 in demo before WORD; word-definition:WORD: require-join, require-space');
        $generator->generate(TerminalSequence::fromNames(['WORD', 'WORD']), null, static fn (int $count): int => 0);
    }

}
