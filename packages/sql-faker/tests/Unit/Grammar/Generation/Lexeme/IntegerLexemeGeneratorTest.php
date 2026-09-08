<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Grammar\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator;
use SqlFaker\Grammar\Generation\Lexeme\Lexeme;
use SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Lexeme\LexemeSequence;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalOccurrence;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;

#[CoversClass(IntegerLexemeGenerator::class)]
#[UsesClass(Lexeme::class)]
#[UsesClass(LexemeCandidates::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(LexemeSequence::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalOccurrence::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class IntegerLexemeGeneratorTest extends TestCase
{
    #[DataProvider('providerUnsigned64')]
    public function testAcceptsUsesTheExactUnsignedMagnitudeInsteadOfAPhpIntegerCast(string $value, bool $expected): void
    {
        $generator = new IntegerLexemeGenerator('UINT64', '9223372036854775808', '18446744073709551615', ['18446744073709551615'], 'scanner:uint64');
        self::assertSame($expected, $generator->accepts($value));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function providerUnsigned64(): iterable
    {
        yield 'minimum' => ['9223372036854775808', true];
        yield 'maximum' => ['18446744073709551615', true];
        yield 'below minimum' => ['9223372036854775807', false];
        yield 'above maximum' => ['18446744073709551616', false];
        yield 'shorter magnitude' => ['1844674407370955161', false];
        yield 'longer magnitude' => ['100000000000000000000', false];
        yield 'leading zeroes' => ['00018446744073709551615', true];
        yield 'fraction' => ['9223372036854775808.0', false];
        yield 'exponent' => ['1e19', false];
        yield 'negative' => ['-9223372036854775808', false];
        yield 'signed spelling' => ['+9223372036854775808', false];
        yield 'whitespace' => [' 9223372036854775808', false];
        yield 'empty' => ['', false];
    }

    public function testAcceptsTreatsZeroAsOneSignificantDigit(): void
    {
        $generator = new IntegerLexemeGenerator('NUM', '0', '2147483647', ['0'], 'scanner:num');
        self::assertTrue($generator->accepts('0'));
        self::assertTrue($generator->accepts('000'));
        self::assertTrue($generator->accepts('2147483647'));
        self::assertFalse($generator->accepts('2147483648'));
    }

    public function testGeneratePreservesExplicitLeadingZeroesAndTheirSourceOccurrence(): void
    {
        $generator = new IntegerLexemeGenerator('NUM', '0', '2147483647', ['1'], 'scanner:num');
        $input = new LexemeInput(TerminalSequence::fromNames(['NUM']), 0, new ResolvedOutput(), '00037');
        $result = $generator->generate($input);
        self::assertNotNull($result);
        $candidate = [...$result->sequences()][0];
        self::assertSame('00037', $candidate->lexemes[0]->text);
        self::assertSame($input->terminal(), $candidate->lexemes[0]->origin);
    }

    public function testGenerateSeparatesAnInvalidValueFromNonApplicability(): void
    {
        $generator = new IntegerLexemeGenerator('NUM', '0', '2147483647', ['1'], 'scanner:num');
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['NUM']), 0, new ResolvedOutput(), '2147483648'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['LONG_NUM']), 0, new ResolvedOutput())));
    }
}
