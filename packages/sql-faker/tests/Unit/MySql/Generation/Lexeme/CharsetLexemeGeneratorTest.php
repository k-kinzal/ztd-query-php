<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator;

#[CoversClass(CharsetLexemeGenerator::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
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
        $right = new ResolvedOutput([new \SqlFaker\Grammar\Generation\Output\OutputPart(new \SqlFaker\Grammar\Generation\Lexeme\Lexeme("X'ff'", 'number', $tokens->terminals[1], 'hex'), '', 'hex')]);
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
