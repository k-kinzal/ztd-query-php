<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator;

#[CoversClass(CharsetLexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\OutputPart::class)]
#[UsesClass(\SqlFaker\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\RadixDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\Utf8::class)]
#[UsesClass(\SqlFaker\Generation\Value\WordDomain::class)]
final class CharsetLexemeGeneratorTest extends TestCase
{
    #[DataProvider('providerEncodings')]
    public function testAcceptsChecksTheSelectedCharsetRatherThanAssumingUtf8(string $charset, string $bytes, bool $expected): void
    {
        self::assertSame($expected, (new CharsetLexemeGenerator())->accepts($charset, $bytes));
    }

    /**
     * @return list<array{string, string, bool}>
     */
    public static function providerEncodings(): array
    {
        return [['_utf8mb4', "\xff", false], ['_UTF8MB4', '😀', true], ['_utf8mb3', '猫', true], ['_utf8mb3', '😀', false], ['_ascii', "\0\x7f", true], ['_ascii', 'é', false], ['_latin1', "\xff", true], ['_binary', "\xff", true], ['unknown', 'a', false]];
    }

    #[DataProvider('providerBytes')]
    public function testBytesDecodesQuotedAndPrefixBinaryFormsWithoutDroppingLeadingPartialBytes(string $literal, string $bytes): void
    {
        self::assertSame($bytes, (new CharsetLexemeGenerator())->bytes($literal));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function providerBytes(): array
    {
        return [["X'ffa600'", "\xff\xa6\0"], ['0x123', "\x01\x23"], ["X''", ''], ["B'111'", "\x07"], ['0b100000001', "\x01\x01"], ["B''", ''], ["'猫'", "'猫'"]];
    }

    public function testGenerateKeepsOnlyCharsetsCompatibleWithTheResolvedLiteral(): void
    {
        $tokens = TerminalSequence::fromNames(['UNDERSCORE_CHARSET', 'HEX_NUM']);
        $right = new ResolvedOutput([new \SqlFaker\Generation\Lexeme\OutputPart(new \SqlFaker\Generation\Lexeme\Lexeme("X'ff'", 'number', $tokens->terminals[1], 'hex'), '', 'hex')]);
        $generator = new CharsetLexemeGenerator();
        $result = $generator->generate(new LexemeInput($tokens, 0, $right));
        self::assertNotNull($result);
        self::assertSame(['_latin1', '_binary'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        $invalid = $generator->generate(new LexemeInput($tokens, 0, $right, '_utf8mb4'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
        self::assertNull($generator->generate(new LexemeInput($tokens, 1, new ResolvedOutput())));
    }
}
