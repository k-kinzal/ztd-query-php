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

    /**
     * @param list<int> $decisions
     * @param list<string> $expected
     */
    #[DataProvider('providerConstructedBoundaries')]
    public function testCreateConstructsScannerBoundaryValues(string $terminal, array $decisions, array $expected): void
    {
        $values = new \SqlFaker\Grammar\Generation\Value\ValueChoices(static function (int $count) use (&$decisions): int {
            return array_shift($decisions) ?? $count - 1;
        });
        $result = (new ValueDefinitions())->create()->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), values: $values));
        self::assertNotNull($result);
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{string, list<int>, list<string>}>
     */
    public static function providerConstructedBoundaries(): iterable
    {
        $minimum = [1, ...array_fill(0, 512, 0)];
        $padding = str_repeat('0', 16);

        yield ['IDENT', $minimum, ['_sf']];
        yield ['IDENT', [], ['_sf' . str_repeat('$', 60)]];
        yield ['IDENT_QUOTED', $minimum, ['`a`']];
        yield ['IDENT_QUOTED', [], ['`' . str_repeat('猫', 64) . '`']];
        yield ['LEX_HOSTNAME', $minimum, ['a']];
        yield ['LEX_HOSTNAME', [], [str_repeat('$', 64)]];
        yield ['TEXT_STRING', $minimum, ["''"]];
        yield ['TEXT_STRING', [], ["'" . str_repeat('猫', 255) . "'"]];
        yield ['NCHAR_STRING', $minimum, ["N''"]];
        yield ['NCHAR_STRING', [], ["N'" . str_repeat('猫', 255) . "'"]];
        yield ['NUM', $minimum, ['0']];
        yield ['NUM', [], [$padding . '2147483647']];
        yield ['LONG_NUM', $minimum, ['2147483648']];
        yield ['LONG_NUM', [], [$padding . '9223372036854775807']];
        yield ['ULONGLONG_NUM', $minimum, ['9223372036854775808']];
        yield ['ULONGLONG_NUM', [], [$padding . '18446744073709551615']];
        yield ['DECIMAL_NUM', $minimum, ['18446744073709551616', '1.5']];
        yield ['DECIMAL_NUM', [], [$padding . str_repeat('9', 65), $padding . '18446744073709551615.' . str_repeat('9', 30)]];
        yield ['FLOAT_NUM', $minimum, ['0.e+0']];
        yield ['FLOAT_NUM', [], [$padding . '18446744073709551615.' . str_repeat('9', 30) . 'E-' . $padding . '308']];
        yield ['HEX_NUM', $minimum, ['0x0']];
        yield ['HEX_NUM', [], ["X'" . str_repeat('F', 32) . "'"]];
        yield ['BIN_NUM', $minimum, ['0b0']];
        yield ['BIN_NUM', [], ["B'" . str_repeat('1', 64) . "'"]];

        foreach (range(1, 127) as $byte) {
            $encoded = str_replace(['\\', "'"], ['\\\\', "''"], chr($byte));
            yield ['TEXT_STRING', [1, 1, $byte - 1], ["'" . $encoded . "'"]];
            yield ['NCHAR_STRING', [1, 1, $byte - 1], ["N'" . $encoded . "'"]];
        }
    }

    /**
     * @param list<int> $decisions
     */
    #[DataProvider('providerIntroducedBoundaries')]
    public function testBinaryAndStringsConstructCompleteAsciiValuesAfterIntroducers(string $terminal, array $decisions, string $expected): void
    {
        $values = new \SqlFaker\Grammar\Generation\Value\ValueChoices(static function (int $count) use (&$decisions): int {
            return array_shift($decisions) ?? $count - 1;
        });
        $result = (new ValueDefinitions())->create()->generate(new LexemeInput(TerminalSequence::fromNames(['UNDERSCORE_CHARSET', $terminal]), 1, new ResolvedOutput(), values: $values));
        self::assertNotNull($result);
        self::assertSame([$expected], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{string, list<int>, string}>
     */
    public static function providerIntroducedBoundaries(): iterable
    {
        yield ['TEXT_STRING', [], "'" . str_repeat("\x7f", 255) . "'"];
        yield ['TEXT_STRING', [1, 0], "''"];
        yield ['HEX_NUM', [], "X'" . str_repeat('7f', 16) . "'"];
        yield ['HEX_NUM', [1, 0], '0x' . str_repeat('7f', 16)];
        yield ['HEX_NUM', [1, 0, 0, 0], '0x00'];
        yield ['HEX_NUM', [1, 1, 0], "X''"];
        yield ['BIN_NUM', [], "B'" . str_repeat('01111111', 8) . "'"];
        yield ['BIN_NUM', [1, 0], '0b' . str_repeat('01111111', 8)];
        yield ['BIN_NUM', [1, 0, 0, 0], '0b00000000'];
        yield ['BIN_NUM', [1, 1, 0], "B''"];
        foreach (range(1, 127) as $byte) {
            $encoded = str_replace(['\\', "'"], ['\\\\', "''"], chr($byte));
            yield ['TEXT_STRING', [1, 1, $byte - 1], "'" . $encoded . "'"];
        }
    }
}
