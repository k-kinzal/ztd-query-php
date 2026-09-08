<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(ChoiceLexemeGenerator::class)]
#[UsesClass(LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class ChoiceLexemeGeneratorTest extends TestCase
{
    public function testGenerateAllMatchingChildrenContributeInDeclarationOrder(): void
    {
        $choice = new ChoiceLexemeGenerator(
            new MatchingLexemeGenerator('OTHER', new FixedLexemeGenerator('unused', 'keyword', 'other')),
            new FixedLexemeGenerator('CURRENT_TIMESTAMP', 'keyword', 'now-keyword'),
            new FixedLexemeGenerator('NOW', 'function', 'now-function'),
        );
        $input = new LexemeInput(TerminalSequence::fromNames(['NOW_SYM']), 0, new ResolvedOutput());
        $result = $choice->generate($input);
        self::assertNotNull($result);
        self::assertSame(['CURRENT_TIMESTAMP', 'NOW'], array_map(
            static fn ($candidate): string => $candidate->lexemes[0]->text,
            [...$result->sequences()],
        ));
    }

    public function testAnApplicableEmptyStreamDiffersFromNoApplicableChild(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['X']), 0, new ResolvedOutput());
        self::assertNull((new ChoiceLexemeGenerator())->generate($input));
        $empty = $this->createMock(LexemeGenerator::class);
        $empty->method('generate')->willReturn(LexemeCandidates::of());
        $result = (new ChoiceLexemeGenerator($empty))->generate($input);
        self::assertNotNull($result);
        self::assertSame([], [...$result->sequences()]);
    }

    public function testSourcesIncludesEveryEquivalentCandidateAndExcludesDifferentOutputs(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['WORD']), 0, new ResolvedOutput());
        $a = (new FixedLexemeGenerator('word', 'keyword', 'source:a'))->generate($input);
        $b = (new FixedLexemeGenerator('word', 'keyword', 'source:b'))->generate($input);
        $other = (new FixedLexemeGenerator('other', 'keyword', 'source:c'))->generate($input);
        $key = [...$a->sequences()][0]->key();
        self::assertSame(['source:a:word', 'source:b:word'], [...(new ChoiceLexemeGenerator())->sources([$a, $b, $other], $key)]);
    }

}
