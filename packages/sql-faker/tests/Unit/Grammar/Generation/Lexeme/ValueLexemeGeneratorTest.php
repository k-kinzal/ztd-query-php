<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(ValueLexemeGenerator::class)]
#[UsesClass(LexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
final class ValueLexemeGeneratorTest extends TestCase
{
    public function testGenerateAcceptsExplicitValuesBeyondDefaultRepresentatives(): void
    {
        $generator = new ValueLexemeGenerator('NUMBER', new \SqlFaker\Grammar\Generation\Value\CharacterDomain(str_split('0123456789'), 1, 20), ['1'], 'number', 'scanner:digits');
        $input = new LexemeInput(TerminalSequence::fromNames(['NUMBER']), 0, new ResolvedOutput(), '983');
        $result = $generator->generate($input);
        self::assertNotNull($result);
        $candidate = [...$result->sequences()][0];
        self::assertSame('983', $candidate->lexemes[0]->text);
        self::assertSame($input->terminal(), $candidate->lexemes[0]->origin);
    }

    public function testGenerateRejectsAnInvalidValueWithoutFallingBackToADefault(): void
    {
        $generator = new ValueLexemeGenerator('NUMBER', new \SqlFaker\Grammar\Generation\Value\CharacterDomain(str_split('0123456789'), 1, 20), ['1'], 'number', 'scanner:digits');
        $input = new LexemeInput(TerminalSequence::fromNames(['NUMBER']), 0, new ResolvedOutput(), '12x');
        $result = $generator->generate($input);
        self::assertNotNull($result);
        self::assertSame([], [...$result->sequences()]);
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['STRING']), 0, new ResolvedOutput())));
    }

}
