<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;

#[CoversClass(LexemeCandidates::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
final class LexemeCandidatesTest extends TestCase
{
    public function testOfDistinguishesNoCandidatesFromOneEmptyOutputCandidate(): void
    {
        self::assertSame([], [...LexemeCandidates::of()->sequences()]);
        $empty = new LexemeSequence([], 'eof');
        self::assertSame([$empty], [...LexemeCandidates::of($empty)->sequences()]);
    }

    public function testSequencesDefersEvaluationAndCanBeTraversedRepeatedly(): void
    {
        $evaluations = 0;
        $sequence = new LexemeSequence([], 'marker');
        $candidates = new LexemeCandidates(static function () use (&$evaluations, $sequence): iterable {
            ++$evaluations;
            yield $sequence;
        });
        self::assertSame(0, $evaluations);
        self::assertSame([$sequence], [...$candidates->sequences()]);
        self::assertSame([$sequence], [...$candidates->sequences()]);
        self::assertSame(2, $evaluations);
    }
}
