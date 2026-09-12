<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Generation\Lexeme\LexemeInput;
use SqlFaker\Generation\Lexeme\ResolvedOutput;
use SqlFaker\Generation\Output\SqlSerializer;
use SqlFaker\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\DefinitionFactory;

#[CoversClass(DefinitionFactory::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SqlSerializer::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Generation\Plan\GenerationPlan::class)]
#[UsesClass(\SqlFaker\Generation\Plan\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\SequenceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\OutputPart::class)]
#[UsesClass(\SqlFaker\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Output\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\KeywordPhraseSpacingRule::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Generation\Lexeme\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\VersionCase::class)]
#[UsesClass(\SqlFaker\Generation\Candidate\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Generation\Exception\LexicalException::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\BoundedIntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\FactorLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\PrecisionLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ReplicationTablePatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\SizeNumberLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\VariableSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Value\RadixDomain::class)]
#[UsesClass(\SqlFaker\Generation\Value\Utf8::class)]
#[UsesClass(\SqlFaker\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersion::class)]
#[UsesClass(\SqlFaker\Generation\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\LexicalDefinition::class)]
final class DefinitionFactoryTest extends TestCase
{
    public function testCreateCombinesLexicalOutputAndBoundaryDecisions(): void
    {
        $pipeline = (new DefinitionFactory())->create('mysql-8.4.7')->pipeline;
        $result = $pipeline->generate(TerminalSequence::fromNames(['NUM', 'END_OF_INPUT']), null, static fn (int $count): int => 0);
        self::assertSame('1', (new SqlSerializer())->serialize($result->pieces()));
    }

    public function testLexemesRejectsAnUnreviewedVersion(): void
    {
        $this->expectException(RuntimeException::class);
        (new DefinitionFactory())->create('future-version')->lexemes;
    }

    #[DataProvider('providerKeyBlockSizes')]
    public function testLexemesUsesTheTwoByteKeyBlockSizeDomain(string $version, string $value, bool $valid): void
    {
        $generator = (new DefinitionFactory())->create($version)->lexemes;
        $candidates = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['KEY_BLOCK_SIZE_NUMBER']), 0, new ResolvedOutput(), $value));
        self::assertNotNull($candidates);
        self::assertSame($valid ? [$value] : [], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$candidates->sequences()]));
    }

    /**
     * @return iterable<array{string, string, bool}>
     */
    public static function providerKeyBlockSizes(): iterable
    {
        foreach (['mysql-5.6.51', 'mysql-5.7.44', 'mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'] as $version) {
            foreach ([['0', true], ['1', true], ['65535', true], ['0xffff', true], ["X'ffff'", true], ['65536', false], ['0x10000', false], ['2147483648', false]] as [$value, $valid]) {
                yield [$version, $value, $valid];
            }
        }
    }
    #[DataProvider('providerNumericLimits')]
    public function testLexemesKeepsParserNumericBoundaries(string $terminal, string $value, bool $valid): void
    {
        $generator = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes;
        $candidates = $generator->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), $value));
        self::assertNotNull($candidates);
        self::assertSame($valid ? [$value] : [], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$candidates->sequences()]));
    }

    /**
     * @return list<array{string, string, bool}>
     */
    public static function providerNumericLimits(): array
    {
        return [

            ['WEIGHT_STRING_LENGTH', '0', false], ['WEIGHT_STRING_LENGTH', '1', true],
            ['WEIGHT_STRING_LENGTH', '2147483647', true], ['WEIGHT_STRING_LENGTH', '2147483648', false],
            ['STATS_SAMPLE_PAGES_NUMBER', '0', false], ['STATS_SAMPLE_PAGES_NUMBER', '1', true],
            ['STATS_SAMPLE_PAGES_NUMBER', '65535', true], ['STATS_SAMPLE_PAGES_NUMBER', '65536', false],
            ['SOURCE_DELAY_NUMBER', '0', true], ['SOURCE_DELAY_NUMBER', '2147483647', true],
            ['SOURCE_DELAY_NUMBER', '2147483648', false],
            ['DISPLAY_WIDTH_NUMBER', '0', true],
            ['DISPLAY_WIDTH_NUMBER', '255', true],
            ['DISPLAY_WIDTH_NUMBER', '256', false],
            ['DISPLAY_WIDTH_NUMBER', '-1', false],
            ['BIT_WIDTH_NUMBER', '0', false],
            ['BIT_WIDTH_NUMBER', '1', true],
            ['BIT_WIDTH_NUMBER', '64', true],
            ['BIT_WIDTH_NUMBER', '65', false],
            ['BIT_WIDTH_NUMBER', '-1', false],
            ['PARTITION_COUNT_NUMBER', '0', false],
            ['PARTITION_COUNT_NUMBER', '1', true],
            ['PARTITION_COUNT_NUMBER', '4294967295', true],
            ['PARTITION_COUNT_NUMBER', '4294967296', false],
            ['DECIMAL_PRECISION_NUMBER', '0', true],
            ['DECIMAL_PRECISION_NUMBER', '65', true],
            ['DECIMAL_PRECISION_NUMBER', '66', false],
            ['DECIMAL_PRECISION_NUMBER', '-1', false],
            ['FLOAT_PRECISION_NUMBER', '0', true],
            ['FLOAT_PRECISION_NUMBER', '53', true],
            ['FLOAT_PRECISION_NUMBER', '54', false],
            ['FLOAT_PRECISION_NUMBER', '-1', false],
            ['VARCHAR_LENGTH_NUMBER', '0', true],
            ['VARCHAR_LENGTH_NUMBER', '65535', true],
            ['VARCHAR_LENGTH_NUMBER', '65536', false],
            ['VARCHAR_LENGTH_NUMBER', '-1', false],
            ['FIELD_LENGTH_NUMBER', '0', true],
            ['FIELD_LENGTH_NUMBER', '4294967295', true],
            ['FIELD_LENGTH_NUMBER', '4294967296', false],
            ['FIELD_LENGTH_NUMBER', '-1', false],
            ['AVG_ROW_LENGTH_NUMBER', '0', true], ['AVG_ROW_LENGTH_NUMBER', '4294967295', true],
            ['AVG_ROW_LENGTH_NUMBER', '4294967296', false], ['AVG_ROW_LENGTH_NUMBER', '-1', false],
            ['KEY_ALGORITHM_NUMBER', '1', true], ['KEY_ALGORITHM_NUMBER', '2', true],
            ['KEY_ALGORITHM_NUMBER', '0x02', true], ['KEY_ALGORITHM_NUMBER', '0', false], ['KEY_ALGORITHM_NUMBER', '3', false],
            ['YEAR_WIDTH_NUMBER', '4', true], ['YEAR_WIDTH_NUMBER', '04', true],
            ['YEAR_WIDTH_NUMBER', '2', false], ['YEAR_WIDTH_NUMBER', '5', false],
        ];
    }

    public function testValuesLeavesUnknownTerminalsUnclaimed(): void
    {
        self::assertNull((new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['UNKNOWN']), 0, new ResolvedOutput())));
    }

    public function testNamesChecksSourceDelimiterOrValueBoundariesValue(): void
    {
        $generator = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['IDENT_QUOTED']), 0, new ResolvedOutput(), '`a``b`'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['IDENT_QUOTED']), 0, new ResolvedOutput(), '`unclosed'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('`a``b`', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testStringsChecksSourceDelimiterOrValueBoundariesValue(): void
    {
        $generator = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['TEXT_STRING']), 0, new ResolvedOutput(), "'a''b'"));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['TEXT_STRING']), 0, new ResolvedOutput(), "'unclosed"));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame("'a''b'", [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testNumbersChecksSourceDelimiterOrValueBoundariesValue(): void
    {
        $generator = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['NUM']), 0, new ResolvedOutput(), '2147483647'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['NUM']), 0, new ResolvedOutput(), '2147483648'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('2147483647', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testBinaryChecksSourceDelimiterOrValueBoundariesValue(): void
    {
        $generator = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['HEX_NUM']), 0, new ResolvedOutput(), "X'0f'"));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['HEX_NUM']), 0, new ResolvedOutput(), "X'f'"));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame("X'0f'", [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    #[DataProvider('providerValueTokensValue')]
    public function testCreateOffersACompleteCandidateForEverySourceValueTokenValue(string $terminal): void
    {
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertNotEmpty([...$result->sequences()]);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerValueTokensValue(): array
    {
        return [['IDENT'], ['IDENT_QUOTED'], ['LEX_HOSTNAME'], ['UNDERSCORE_CHARSET'], ['TEXT_STRING'], ['NCHAR_STRING'], ['NUM'], ['LONG_NUM'], ['ULONGLONG_NUM'], ['DECIMAL_NUM'], ['FLOAT_NUM'], ['HEX_NUM'], ['BIN_NUM']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerLexicalFormsValue')]
    public function testCreateRetainsValidSourceSpellingsAndRejectsMalformedOnesValue(string $terminal, string $spelling, array $expected): void
    {
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), $spelling));
        self::assertNotNull($result);
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return list<array{string, string, list<string>}>
     */
    public static function providerLexicalFormsValue(): array
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

    public function testBinaryDomainKeepsExplicitByteSpellingsAvailableForCompatibleCharsetsValue(): void
    {
        $input = new LexemeInput(TerminalSequence::fromNames(['HEX_NUM']), 0, new ResolvedOutput(), "X'ff'");
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate($input);
        self::assertNotNull($result);
        self::assertSame("X'ff'", [...$result->sequences()][0]->lexemes[0]->text);
    }

    public function testStringsKeepsConstructedIntroducedValuesValidUnderAnExplicitAsciiCharsetValue(): void
    {
        $tokens = TerminalSequence::fromNames(['UNDERSCORE_CHARSET', 'TEXT_STRING']);
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput($tokens, 1, new ResolvedOutput(), values: new \SqlFaker\Generation\Value\ValueChoices(static fn (int $count): int => $count - 1)));
        self::assertNotNull($result);
        self::assertSame(1, preg_match('/\A[\x00-\x7f]*\z/D', [...$result->sequences()][0]->lexemes[0]->text));
    }

    /**
     * @param list<int> $decisions
     * @param list<string> $expected
     */
    #[DataProvider('providerConstructedBoundariesValue')]
    public function testCreateConstructsScannerBoundaryValuesValue(string $terminal, array $decisions, array $expected): void
    {
        $values = new \SqlFaker\Generation\Value\ValueChoices(static function (int $count) use (&$decisions): int {
            return array_shift($decisions) ?? $count - 1;
        });
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), values: $values));
        self::assertNotNull($result);
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{string, list<int>, list<string>}>
     */
    public static function providerConstructedBoundariesValue(): iterable
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
        yield ['NCHAR_STRING', [], ["n'" . str_repeat('猫', 255) . "'"]];
        yield ['NUM', $minimum, ['0']];
        yield ['NUM', [], [$padding . '2147483647']];
        yield ['LONG_NUM', $minimum, ['2147483648']];
        yield ['LONG_NUM', [], [$padding . '9223372036854775807']];
        yield ['ULONGLONG_NUM', $minimum, ['9223372036854775808']];
        yield ['ULONGLONG_NUM', [], [$padding . '18446744073709551615']];
        yield ['DECIMAL_NUM', $minimum, ['18446744073709551616', '1.5']];
        yield ['DECIMAL_NUM', [], [$padding . str_repeat('9', 65), '.' . str_repeat('9', 20)]];
        yield ['FLOAT_NUM', $minimum, ['0e0']];
        yield ['FLOAT_NUM', [], ['.' . str_repeat('9', 20) . 'E-999']];
        yield ['HEX_NUM', $minimum, ['0x0']];
        yield ['HEX_NUM', [], ["X'" . str_repeat('F', 32) . "'"]];
        yield ['BIN_NUM', $minimum, ['0b0']];
        yield ['BIN_NUM', [], ["B'" . str_repeat('1', 64) . "'"]];

        foreach (range(1, 127) as $byte) {
            $encoded = str_replace(['\\', "'"], ['\\\\', "''"], chr($byte));
            yield ['TEXT_STRING', [1, 0, 1, $byte - 1], ["'" . $encoded . "'"]];
            yield ['NCHAR_STRING', [1, 0, 1, $byte - 1], ["N'" . $encoded . "'"]];
        }
    }

    /**
     * @param list<int> $decisions
     */
    #[DataProvider('providerIntroducedBoundariesValue')]
    public function testBinaryAndStringsConstructCompleteAsciiValuesAfterIntroducersValue(string $terminal, array $decisions, string $expected): void
    {
        $values = new \SqlFaker\Generation\Value\ValueChoices(static function (int $count) use (&$decisions): int {
            return array_shift($decisions) ?? $count - 1;
        });
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['UNDERSCORE_CHARSET', $terminal]), 1, new ResolvedOutput(), values: $values));
        self::assertNotNull($result);
        self::assertSame([$expected], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{string, list<int>, string}>
     */
    public static function providerIntroducedBoundariesValue(): iterable
    {
        yield ['TEXT_STRING', [], "'" . str_repeat("\x7f", 255) . "'"];
        yield ['TEXT_STRING', [1, 0, 0], "''"];
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
            yield ['TEXT_STRING', [1, 0, 1, $byte - 1], "'" . $encoded . "'"];
        }
    }

    public function testSymbolsKeepsTheCompleteOutput(): void
    {
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['SET_VAR']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame(':=', implode(' ', array_map(static fn ($lexeme): string => $lexeme->text, [...$result->sequences()][0]->lexemes)));
    }

    public function testJsonKeepsTheCompleteDeclaredOutputSymbol(): void
    {
        $result = (new DefinitionFactory())->create('mysql-5.7.44')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['JSON_SEPARATOR_SYM']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame('->', implode(' ', array_map(static fn ($lexeme): string => $lexeme->text, [...$result->sequences()][0]->lexemes)));
    }

    public function testPhrasesKeepsTheCompleteDeclaredOutputSymbol(): void
    {
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['WITH_ROLLUP_SYM']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame('WITH ROLLUP', implode(' ', array_map(static fn ($lexeme): string => $lexeme->text, [...$result->sequences()][0]->lexemes)));
    }

    public function testSelectorsKeepsTheCompleteDeclaredOutputSymbol(): void
    {
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['GRAMMAR_SELECTOR_EXPR']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame('', implode(' ', array_map(static fn ($lexeme): string => $lexeme->text, [...$result->sequences()][0]->lexemes)));
    }

    public function testPhraseKeepsBothWordsInTheSameOriginalOccurrenceSymbol(): void
    {
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['WITH_ROLLUP_SYM']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        $lexemes = [...$result->sequences()][0]->lexemes;
        self::assertSame($lexemes[0]->origin, $lexemes[1]->origin);
        self::assertSame($lexemes[0]->phrase, $lexemes[1]->phrase);
    }

    public function testNonOutputDoesNotMisclassifyAStatementKeywordAsAMarkerSymbol(): void
    {
        $markers = (new DefinitionFactory())->create('mysql-8.4.7')->nonOutput;
        self::assertContains('END_OF_INPUT', $markers);
        self::assertContains('GRAMMAR_SELECTOR_EXPR', $markers);
        self::assertNotContains('SELECT_SYM', $markers);
    }

    public function testDollarStringsUsesTheFirstMatchingDelimiter(): void
    {
        $generator = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['DOLLAR_QUOTED_STRING_SYM']), 0, new ResolvedOutput(), '$tag$a$other$b$tag$'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['DOLLAR_QUOTED_STRING_SYM']), 0, new ResolvedOutput(), '$tag$a$tag$b$tag$'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertCount(1, [...$valid->sequences()]);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testDollarVersionsDoesNotAssumeFutureCompatibility(): void
    {
        $definitions = new DefinitionFactory();
        self::assertNotContains('mysql-8.0.44', $definitions->create('mysql-8.4.7')->dollarVersions);
        self::assertContains('mysql-8.1.0', $definitions->create('mysql-8.4.7')->dollarVersions);
        $this->expectException(RuntimeException::class);
        $definitions->create('mysql-9.2.0');
    }

    /**
     * @param list<int> $decisions
     */
    #[DataProvider('providerConstructedStringsDollarString')]
    public function testCreateConstructsCompleteDollarDelimitersAndUnescapedBodiesDollarString(array $decisions, string $expected): void
    {
        $values = new \SqlFaker\Generation\Value\ValueChoices(static function (int $count) use (&$decisions): int {
            return array_shift($decisions) ?? $count - 1;
        });
        $result = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['DOLLAR_QUOTED_STRING_SYM']), 0, new ResolvedOutput(), values: $values));
        self::assertNotNull($result);
        self::assertSame([$expected], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{list<int>, string}>
     */
    public static function providerConstructedStringsDollarString(): iterable
    {
        yield [[1, 0, 0], '$$$$'];
        yield [[], '$' . str_repeat('猫', 16) . '$' . str_repeat('猫', 255) . '$' . str_repeat('猫', 16) . '$'];
        foreach ([...range(1, 35), ...range(37, 127)] as $index => $byte) {
            yield [[1, 0, 1, $index], '$$' . chr($byte) . '$$'];
        }
    }

    public function testContextualValuesRestrictsTheDomain(): void
    {
        $generator = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes;
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ROTATE_KEY_ENGINE']), 0, new ResolvedOutput(), null));
        self::assertNotNull($result);
        self::assertSame(['INNODB', 'BINLOG'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ROTATE_KEY_ENGINE']), 0, new ResolvedOutput(), 'unknown'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testContextualWordPreservesExplicitCase(): void
    {
        $generator = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes;
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ROTATE_KEY_ENGINE']), 0, new ResolvedOutput(), 'innodb'));
        self::assertNotNull($result);
        self::assertSame('innodb', [...$result->sequences()][0]->lexemes[0]->text);
    }
    public function testCreateEnumeratesOnlySourceAcceptedTernaryValuesContextualValue(): void
    {
        $generator = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes;
        $input = new LexemeInput(TerminalSequence::fromNames(['TERNARY_OPTION_NUMBER']), 0, new ResolvedOutput());
        $result = $generator->generate($input);
        self::assertNotNull($result);
        self::assertSame(['0', '1'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        $invalid = $generator->generate(new LexemeInput($input->terminals, 0, new ResolvedOutput(), '2'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }

    /**
     * @param list<string> $spellings
     */
    #[DataProvider('providerContextualDomainsContextualValue')]
    public function testCreatePreservesTheVersionedParserValueDomainsContextualValue(string $version, string $terminal, array $spellings): void
    {
        $result = (new DefinitionFactory())->create($version)->lexemes->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame($spellings, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{string, string, list<string>}>
     */
    public static function providerContextualDomainsContextualValue(): iterable
    {
        yield ['mysql-5.7.44', 'ROTATE_KEY_ENGINE', ['INNODB']];
        yield ['mysql-5.7.44', 'REPLICATION_TABLE_PATTERN', ["'db.table'", "'db.%'", "'%.table'"]];
        foreach (['mysql-8.0.44', 'mysql-8.1.0', 'mysql-8.2.0', 'mysql-8.3.0', 'mysql-8.4.7', 'mysql-9.0.1', 'mysql-9.1.0'] as $version) {
            yield [$version, 'REPLICATION_TABLE_PATTERN', ["'db.table'", "'db.%'", "'%.table'"]];
            yield [$version, 'REDO_ENGINE', ['INNODB']];
            yield [$version, 'REDO_LOG_NAME', ['REDO_LOG']];
            yield [$version, 'LOAD_COUNT_NAME', ['COUNT']];
            yield [$version, 'LOAD_SOURCE_COUNT', ['1']];
            yield [$version, 'REPLICATION_FLAG_NUMBER', ['0', '1']];
            yield [$version, 'BINLOG_RESET_INDEX', ['1', '2000000000', "X'01'"]];
        }
    }

    #[DataProvider('providerLiteralNamesContextualValue')]
    public function testCreateRetainsContextualSpellingsAndTheirSourceDefinition(string $spelling): void
    {
        $generator = (new DefinitionFactory())->create('mysql-8.4.7')->lexemes;
        $input = TerminalSequence::fromNames(['REDO_LOG_NAME']);
        $result = $generator->generate(new LexemeInput($input, 0, new ResolvedOutput(), $spelling));
        self::assertNotNull($result);
        $candidates = [...$result->sequences()];
        self::assertCount(1, $candidates);
        self::assertSame($spelling, $candidates[0]->lexemes[0]->text);
        self::assertSame('sql/sql_yacc.yy:alter_instance_action', $candidates[0]->lexemes[0]->definition);
        $invalid = $generator->generate(new LexemeInput($input, 0, new ResolvedOutput(), 'axb'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }
    /**
     * @return iterable<array{string}>
     */
    public static function providerLiteralNamesContextualValue(): iterable
    {
        yield ['redo_log'];
        yield ['REDO_LOG'];
    }

    public function testKeywordsKeepsAliasesFunctionCategoriesAndReleaseChangesExplicit(): void
    {
        $factory = new DefinitionFactory();
        $current = $factory->create('mysql-8.4.7');
        self::assertSame(['CURRENT_TIMESTAMP', 'LOCALTIME', 'LOCALTIMESTAMP'], $current->keywords['NOW_SYM']);
        self::assertSame(['NOW'], $current->functions['NOW_SYM']);
        self::assertSame(['BIGINT', 'INT8'], $current->keywords['BIGINT_SYM']);
        self::assertArrayNotHasKey('MASTER_HOST_SYM', $current->keywords);
        self::assertSame(['MASTER_HOST'], $factory->create('mysql-8.3.0')->keywords['MASTER_HOST_SYM']);
        self::assertSame(['BIGINT', 'INT8'], $factory->create('mysql-5.6.51')->keywords['BIGINT']);
        self::assertArrayNotHasKey('VECTOR_SYM', $current->keywords);
        self::assertSame(['VECTOR'], $factory->create('mysql-9.0.1')->keywords['VECTOR_SYM']);
    }
}
