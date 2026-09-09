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
use SqlFaker\MySql\Generation\Lexeme\ValueDefinitions;

#[CoversClass(ValueDefinitions::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
final class ValueDefinitionsTest extends TestCase
{
    public function testCreateLeavesUnknownTerminalsUnclaimed(): void
    {
        self::assertNull((new ValueDefinitions())->create()->generate(new LexemeInput(TerminalSequence::fromNames(['UNKNOWN']), 0, new ResolvedOutput())));
    }

    public function testNamesChecksSourceDelimiterOrValueBoundaries(): void
    {
        $generator = (new ValueDefinitions())->names();
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['IDENT_QUOTED']), 0, new ResolvedOutput(), '`a``b`'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['IDENT_QUOTED']), 0, new ResolvedOutput(), '`unclosed'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('`a``b`', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testStringsChecksSourceDelimiterOrValueBoundaries(): void
    {
        $generator = (new ValueDefinitions())->strings();
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['TEXT_STRING']), 0, new ResolvedOutput(), "'a''b'"));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['TEXT_STRING']), 0, new ResolvedOutput(), "'unclosed"));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame("'a''b'", [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testNumbersChecksSourceDelimiterOrValueBoundaries(): void
    {
        $generator = (new ValueDefinitions())->numbers();
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['NUM']), 0, new ResolvedOutput(), '2147483647'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['NUM']), 0, new ResolvedOutput(), '2147483648'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('2147483647', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testBinaryChecksSourceDelimiterOrValueBoundaries(): void
    {
        $generator = (new ValueDefinitions())->binary();
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['HEX_NUM']), 0, new ResolvedOutput(), "X'0f'"));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['HEX_NUM']), 0, new ResolvedOutput(), "X'f'"));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame("X'0f'", [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    #[DataProvider('providerValueTokens')]
    public function testCreateOffersACompleteCandidateForEverySourceValueToken(string $terminal): void
    {
        $result = (new ValueDefinitions())->create()->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertNotEmpty([...$result->sequences()]);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerValueTokens(): array
    {
        return [['IDENT'], ['IDENT_QUOTED'], ['LEX_HOSTNAME'], ['UNDERSCORE_CHARSET'], ['TEXT_STRING'], ['NCHAR_STRING'], ['NUM'], ['LONG_NUM'], ['ULONGLONG_NUM'], ['DECIMAL_NUM'], ['FLOAT_NUM'], ['HEX_NUM'], ['BIN_NUM']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerLexicalForms')]
    public function testCreateRetainsValidSourceSpellingsAndRejectsMalformedOnes(string $terminal, string $spelling, array $expected): void
    {
        $result = (new ValueDefinitions())->create()->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), $spelling));
        self::assertNotNull($result);
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return list<array{string, string, list<string>}>
     */
    public static function providerLexicalForms(): array
    {
        return [
            ['IDENT', 'alpha_2', ['alpha_2']],
            ['IDENT', '$bad name', []],
            ['LEX_HOSTNAME', 'host.example', ['host.example']],
            ['LEX_HOSTNAME', 'host/name', []],
            ['UNDERSCORE_CHARSET', '_UTF8MB4', ['_UTF8MB4']],
            ['UNDERSCORE_CHARSET', 'utf8mb4', []],
            ['NCHAR_STRING', 'n\'a\\\'b\'', ['n\'a\\\'b\'']],
            ['NCHAR_STRING', 'N\'unclosed', []],
            ['LONG_NUM', '9223372036854775807', ['9223372036854775807']],
            ['LONG_NUM', '2147483647', []],
            ['ULONGLONG_NUM', '9223372036854775808', ['9223372036854775808']],
            ['ULONGLONG_NUM', '18446744073709551616', []],
            ['DECIMAL_NUM', '1', []],
            ['DECIMAL_NUM', '18446744073709551615', []],
            ['DECIMAL_NUM', '18446744073709551616', ['18446744073709551616']],
            ['DECIMAL_NUM', '.75', ['.75']],
            ['DECIMAL_NUM', '1.5e2', []],
            ['FLOAT_NUM', '12.5E-4', ['12.5E-4']],
            ['FLOAT_NUM', '12.5', []],
            ['BIN_NUM', 'b\'001\'', ['b\'001\'']],
            ['BIN_NUM', 'B\'02\'', []],
        ];
    }

    public function testBinaryDomainKeepsExplicitByteSpellingsAvailableForCompatibleCharsets(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['HEX_NUM']), 0, new ResolvedOutput(), "X'ff'");
        $result = (new ValueDefinitions())->binaryDomain(true)->generate($input);
        self::assertNotNull($result);
        self::assertSame("X'ff'", [...$result->sequences()][0]->lexemes[0]->text);
    }

    public function testStringsKeepsConstructedIntroducedValuesValidUnderAnExplicitAsciiCharset(): void
    {
        $tokens = TerminalSequence::fromNames(['UNDERSCORE_CHARSET', 'TEXT_STRING']);
        $result = (new ValueDefinitions())->strings()->generate(new LexemeInput($tokens, 1, new ResolvedOutput(), values: new \SqlFaker\Grammar\Generation\Value\ValueChoices(static fn (int $count): int => $count - 1)));
        self::assertNotNull($result);
        self::assertSame(1, preg_match('/\A[\x00-\x7f]*\z/D', [...$result->sequences()][0]->lexemes[0]->text));
    }
}
