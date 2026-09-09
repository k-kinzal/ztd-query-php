<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Output\BoundaryCompletion;
use SqlFaker\Grammar\Generation\Output\CandidateResolver;
use SqlFaker\Grammar\Generation\Output\OutputPart;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator;
use SqlFaker\Grammar\Generation\Spacing\LexemeBoundary;
use SqlFaker\Grammar\Generation\Spacing\SpacingConstraint;
use SqlFaker\Grammar\Generation\Spacing\SpacingRule;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(BoundaryCompletion::class)]
#[UsesClass(CandidateResolver::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(ReverseLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(SpacingConstraint::class)]
#[UsesClass(LexemeBoundary::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
final class BoundaryCompletionTest extends TestCase
{
    #[DataProvider('providerMarkers')]
    public function testAcceptsOnlyCandidatesWhosePendingLeftBoundaryHasACompletion(bool $marker): void
    {
        $lexemes = self::createStub(LexemeGenerator::class);
        $lexemes->method('generate')->willReturnCallback(static fn (LexemeInput $input): LexemeCandidates => match ($input->terminal()->name) {
            'L' => LexemeCandidates::of(new LexemeSequence([new Lexeme('l', 'identifier', $input->terminal(), 'left')], 'left')),
            'E' => LexemeCandidates::of(new LexemeSequence([], 'marker')),
            default => LexemeCandidates::of(
                new LexemeSequence([new Lexeme('r', 'identifier', $input->terminal(), 'right')], 'join', new SpacingConstraint(SpacingConstraint::JOIN, ['join-required'])),
                new LexemeSequence([new Lexeme('r', 'identifier', $input->terminal(), 'right')], 'space', new SpacingConstraint(SpacingConstraint::SPACE, ['space-required'])),
            ),
        });
        $spacing = self::createStub(SpacingRule::class);
        $spacing->method('apply')->willReturn(new SpacingConstraint(SpacingConstraint::SPACE, ['left-space']));
        $generator = new ReverseLexemeGenerator($lexemes, new CandidateResolver($spacing), 'test');
        $result = $generator->generate(TerminalSequence::fromNames($marker ? ['L', 'E', 'R'] : ['L', 'R']), null, static fn (int $count): int => 0);
        self::assertSame('l r', implode('', $result->pieces()));
        self::assertSame('join', $result->rejections[0]['candidate']);
        self::assertSame(['uncompletable-left-boundary'], $result->rejections[0]['rules']);
    }

    public function testAcceptsUsesTheSampledLeftValueInsteadOfAnUnrelatedDefaultWitness(): void
    {
        $lexemes = self::createStub(LexemeGenerator::class);
        $lexemes->method('generate')->willReturnCallback(static fn (LexemeInput $input): LexemeCandidates => $input->terminal()->name === 'L'
            ? LexemeCandidates::of(new LexemeSequence([new Lexeme($input->values?->value(0, 'sample', new \SqlFaker\Grammar\Generation\Value\CharacterDomain(['a', 'b'], 1, 1)) ?? 'a', 'identifier', $input->terminal(), 'sample')], 'left'))
            : LexemeCandidates::of(new LexemeSequence([new Lexeme('r', 'identifier', $input->terminal(), 'right')], 'join', new SpacingConstraint(SpacingConstraint::JOIN)), new LexemeSequence([new Lexeme('r', 'identifier', $input->terminal(), 'right')], 'space', new SpacingConstraint(SpacingConstraint::SPACE))));
        $spacing = self::createStub(SpacingRule::class);
        $spacing->method('apply')->willReturnCallback(static fn (LexemeBoundary $boundary): SpacingConstraint => new SpacingConstraint($boundary->left->text === 'a' ? SpacingConstraint::JOIN : SpacingConstraint::SPACE));
        $generator = new ReverseLexemeGenerator($lexemes, new CandidateResolver($spacing), 'test');
        $result = $generator->generate(TerminalSequence::fromNames(['L', 'R']), null, static fn (int $count): int => 0, static fn (int $count): int => $count - 1);
        self::assertSame('b r', implode('', $result->pieces()));
        self::assertSame('join', $result->rejections[0]['candidate']);
        self::assertSame(SpacingConstraint::SPACE, $result->parts[0]->allowed);
    }

    public function testAcceptsRejectsMissingOrPlannedAwayWitnesses(): void
    {
        $lexemes = self::createStub(LexemeGenerator::class);
        $lexemes->method('generate')->willReturn(null);
        $spacing = self::createStub(SpacingRule::class);
        $completion = new BoundaryCompletion($lexemes, new CandidateResolver($spacing));
        $sequence = TerminalSequence::fromNames(['L']);
        $pending = new ResolvedOutput([], new SpacingConstraint(SpacingConstraint::SPACE));
        self::assertFalse($completion->accepts($sequence, 0, $pending));
        self::assertFalse($completion->accepts($sequence, -1, $pending));
        self::assertTrue($completion->accepts($sequence, -1, new ResolvedOutput()));
    }

    /**
     * @return iterable<array{bool}>
     */
    public static function providerMarkers(): iterable
    {
        yield [false];
        yield [true];
    }
}
