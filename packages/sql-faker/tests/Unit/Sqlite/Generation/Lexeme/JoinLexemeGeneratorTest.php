<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\OutputPart;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\ProductionOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Sqlite\Generation\Lexeme\JoinLexemeGenerator;

#[CoversClass(JoinLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ProductionOccurrence::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinModifiers::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
final class JoinLexemeGeneratorTest extends TestCase
{
    public function testGenerateKeepsOnlyModifiersCompatibleWithTheSelectedSuffix(): void
    {
        $first = new TerminalOccurrence('JOIN_KW', 2, [0, 1], ['seltablist', 'joinop']);
        $second = new TerminalOccurrence('JOIN_MODIFIER', 3, [0, 1], ['seltablist', 'joinop']);
        $sequence = new TerminalSequence([$first, $second]);
        $right = new ResolvedOutput([new OutputPart(new Lexeme('OUTER', 'join-modifier', $second, 'source'), ' ', 'outer')]);
        $generator = new JoinLexemeGenerator(['INNER', 'LEFT', 'RIGHT', 'FULL', 'OUTER']);
        $result = $generator->generate(new LexemeInput($sequence, 0, $right));
        self::assertNotNull($result);
        self::assertSame(['LEFT', 'RIGHT', 'FULL'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['JOIN_KW']), 0, new ResolvedOutput())));
    }

    public function testHasConditionReadsOnlyTheSameSourceList(): void
    {
        $modifier = new TerminalOccurrence('JOIN_KW', 2, [0, 1], ['seltablist', 'joinop']);
        $condition = new TerminalOccurrence('ON', 4, [0, 3], ['seltablist', 'on_using']);
        $sequence = new TerminalSequence([$modifier, $condition], [], [], [new ProductionOccurrence(3, 0, 'on_using', 1)]);
        self::assertTrue((new JoinLexemeGenerator([]))->hasCondition(new LexemeInput($sequence, 0, new ResolvedOutput())));
        $empty = new TerminalSequence([$modifier], [], [], [new ProductionOccurrence(3, 0, 'on_using', 0)]);
        self::assertFalse((new JoinLexemeGenerator([]))->hasCondition(new LexemeInput($empty, 0, new ResolvedOutput())));
    }
}
