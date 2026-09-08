<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\OutputPart;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Lexeme\HashBoundLexemeGenerator;

#[CoversClass(HashBoundLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(OutputPart::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class HashBoundLexemeGeneratorTest extends TestCase
{
    public function testGenerateMakesBothOrdersReachableAndRejectsRepeatedNames(): void
    {
        $left = new TerminalOccurrence('HASH_BOUND_NAME', 1, [0], ['PartitionBoundSpec']);
        $right = new TerminalOccurrence('HASH_BOUND_NAME', 2, [0], ['PartitionBoundSpec']);
        $sequence = new TerminalSequence([$left, $right]);
        $generator = new HashBoundLexemeGenerator();
        $last = $generator->generate(new LexemeInput($sequence, 1, new ResolvedOutput()));
        self::assertNotNull($last);
        self::assertCount(2, [...$last->sequences()]);
        $suffix = new ResolvedOutput([new OutputPart(new Lexeme('modulus', 'identifier', $right, 'source'), '', 'chosen')]);
        $first = $generator->generate(new LexemeInput($sequence, 0, $suffix));
        self::assertNotNull($first);
        self::assertSame('remainder', [...$first->sequences()][0]->lexemes[0]->text);
        $invalid = $generator->generate(new LexemeInput($sequence, 0, $suffix, 'modulus'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }
}
