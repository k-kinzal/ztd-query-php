<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Output\SqlSerializer;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\PostgreSql\Generation\Lexeme\DefinitionFactory;

#[CoversClass(DefinitionFactory::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SqlSerializer::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\GenerationPlan::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ValueLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\HashBoundLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgLookahead::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Value\OperatorDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\SqlVersion::class)]
#[UsesClass(\SqlFaker\PostgreSql\Generation\Lexeme\LexicalDefinition::class)]
final class DefinitionFactoryTest extends TestCase
{
    public function testCreateCombinesLexicalOutputAndBoundaryDecisions(): void
    {
        $pipeline = (new DefinitionFactory())->create('pg-17.2')->pipeline;
        $result = $pipeline->generate(TerminalSequence::fromNames(['ICONST', 'MODE_TYPE_NAME']), null, static fn (int $count): int => 0);
        self::assertSame('1', (new SqlSerializer())->serialize($result->pieces()));
    }

    public function testLexemesRejectsAnUnreviewedVersion(): void
    {
        $this->expectException(RuntimeException::class);
        (new DefinitionFactory())->create('future-version')->lexemes;
    }

    public function testSymbolsKeepMultiCharacterOperatorsIndivisible(): void
    {
        $result = (new DefinitionFactory())->create('pg-17.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['TYPECAST']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame('::', [...$result->sequences()][0]->lexemes[0]->text);
    }

    public function testNonOutputContainsParserSelectorsOnly(): void
    {
        $markers = (new DefinitionFactory())->create('pg-17.2')->nonOutput;
        self::assertContains('MODE_TYPE_NAME', $markers);
        self::assertNotContains('SELECT', $markers);
    }

    public function testValuesLeavesUnknownTerminalsUnclaimed(): void
    {
        self::assertNull((new DefinitionFactory())->create('pg-17.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['UNDECLARED_TEST_TOKEN']), 0, new ResolvedOutput())));
    }

    public function testNamesChecksSourceDelimiterOrValueBoundariesValue(): void
    {
        $generator = (new DefinitionFactory())->create('pg-17.2')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['IDENT']), 0, new ResolvedOutput(), '"a""b"'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['IDENT']), 0, new ResolvedOutput(), '"unclosed'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('"a""b"', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testStringsChecksSourceDelimiterOrValueBoundariesValue(): void
    {
        $generator = (new DefinitionFactory())->create('pg-17.2')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['SCONST']), 0, new ResolvedOutput(), "'a''b'"));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['SCONST']), 0, new ResolvedOutput(), "'unclosed"));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame("'a''b'", [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testNumbersChecksSourceDelimiterOrValueBoundariesValue(): void
    {
        $generator = (new DefinitionFactory())->create('pg-17.2')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['Op']), 0, new ResolvedOutput(), '?&'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['Op']), 0, new ResolvedOutput(), '/*'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('?&', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    #[DataProvider('providerValueTokensValue')]
    public function testCreateOffersACompleteCandidateForEverySourceValueTokenValue(string $terminal): void
    {
        $result = (new DefinitionFactory())->create('pg-17.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertNotEmpty([...$result->sequences()]);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerValueTokensValue(): array
    {
        return [['IDENT'], ['UIDENT'], ['PARAM'], ['SCONST'], ['USCONST'], ['BCONST'], ['XCONST'], ['ICONST'], ['FCONST'], ['Op']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerLexicalFormsValue')]
    public function testCreateRetainsValidSourceSpellingsAndRejectsMalformedOnesValue(string $terminal, string $spelling, array $expected): void
    {
        $result = (new DefinitionFactory())->create('pg-17.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), $spelling));
        self::assertNotNull($result);
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return list<array{string, string, list<string>}>
     */
    public static function providerLexicalFormsValue(): array
    {
        return [
            ['FLOAT_PRECISION_NUMBER', '53', ['53']],
            ['FLOAT_PRECISION_NUMBER', '54', []],
            ['FLOAT_PRECISION_NUMBER', '0', []],
            ['COLUMN_POSITION_NUMBER', '32767', ['32767']],
            ['COLUMN_POSITION_NUMBER', '32768', []],
            ['COLUMN_POSITION_NUMBER', '0', []],
            ['SCONST', "'ordinary'", ["'ordinary'"]],
            ['SCONST', "'with\0nul'", []],
            ['USCONST', "U&'ordinary'", ["U&'ordinary'"]],
            ['USCONST', "U&'with\0nul'", []],
            ['UIDENT', 'u&"a""b"', ['u&"a""b"']],
            ['UIDENT', 'u&"unterminated', []],
            ['PARAM', '$17', ['$17']],
            ['PARAM', '$', []],
            ['SCONST', 'E\'a\\\'b\'', ['E\'a\\\'b\'']],
            ['SCONST', '\'unclosed', []],
            ['USCONST', 'u&\'a\'\'b\'', ['u&\'a\'\'b\'']],
            ['USCONST', 'u\'wrong prefix\'', []],
            ['BCONST', 'b\'001\'', ['b\'001\'']],
            ['BCONST', 'b\'02\'', []],
            ['XCONST', 'x\'f\'', ['x\'f\'']],
            ['XCONST', 'x\'g\'', []],
            ['ICONST', '1_000', ['1_000']],
            ['ICONST', '1__000', []],
            ['FCONST', '1', []],
            ['FCONST', '2147483647', []],
            ['FCONST', '2147483648', ['2147483648']],
            ['FCONST', '.5e+7', ['.5e+7']],
            ['FCONST', '.5e', []],
            ['Op', '?@', ['?@']],
            ['Op', '/*', []],
            ['Op', '|-', ['|-']],
            ['Op', '--', []],
        ];
    }
    #[DataProvider('providerDollarStringsValue')]
    public function testStringsRecognizesDollarDelimitersLiterallyValue(string $value, bool $valid): void
    {
        $result = (new DefinitionFactory())->create('pg-17.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['SCONST']), 0, new ResolvedOutput(), $value));
        self::assertNotNull($result);
        self::assertSame($valid ? [$value] : [], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{string, bool}>
     */
    public static function providerDollarStringsValue(): iterable
    {
        yield ['$$text$$', true];
        yield ['$tag$text$tag$', true];
        yield ['$_0$a\'b$_0$', true];
        yield ['$tag$text$other$', false];
        yield ['$0$text$0$', false];
        yield ['$$a$$b$$', false];
    }

    /**
     * @param list<int> $decisions
     * @param list<string> $expected
     */
    #[DataProvider('providerConstructedBoundariesValue')]
    public function testCreateConstructsScannerBoundaryValuesValue(string $terminal, array $decisions, array $expected): void
    {
        $values = new \SqlFaker\Grammar\Generation\Value\ValueChoices(static function (int $count) use (&$decisions): int {
            return array_shift($decisions) ?? $count - 1;
        });
        $result = (new DefinitionFactory())->create('pg-17.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), values: $values));
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
        yield ['IDENT', [], ['"' . str_repeat('猫', 63) . '"']];
        yield ['IDENT', [1, 0], ['_sf' . str_repeat('$', 59)]];
        yield ['UIDENT', $minimum, ['U&"a"']];
        yield ['UIDENT', [], ['u&"' . str_repeat('猫', 63) . '"']];
        yield ['PARAM', $minimum, ['$1']];
        yield ['PARAM', [], ['$99999']];
        yield ['SCONST', $minimum, ["''"]];
        yield ['SCONST', [], ['$' . str_repeat('_', 17) . '$' . str_repeat('😀', 255) . '$' . str_repeat('_', 17) . '$']];
        yield ['SCONST', [1, 0], ["'" . str_repeat('😀', 255) . "'"]];
        yield ['SCONST', [1, 1], ["e'" . str_repeat('😀', 255) . "'"]];
        yield ['USCONST', $minimum, ["U&''"]];
        yield ['USCONST', [], ["u&'" . str_repeat('😀', 255) . "'"]];
        yield ['BCONST', $minimum, ["B''"]];
        yield ['BCONST', [], ["b'" . str_repeat('1', 255) . "'"]];
        yield ['XCONST', $minimum, ["X''"]];
        yield ['XCONST', [], ["x'" . str_repeat('F', 255) . "'"]];
        yield ['FLOAT_PRECISION_NUMBER', $minimum, ['1']];
        yield ['FLOAT_PRECISION_NUMBER', [], [$padding . '53']];
        yield ['COLUMN_POSITION_NUMBER', $minimum, ['1']];
        yield ['COLUMN_POSITION_NUMBER', [], [$padding . '32767']];
        yield ['ICONST', $minimum, ['0']];
        yield ['ICONST', [], [$padding . '2147483647']];
        yield ['FCONST', $minimum, ['2147483648', '1.5', '.5', '1e2']];
        yield ['FCONST', [], [$padding . str_repeat('9', 65), '.' . str_repeat('9', 20) . 'E-999']];
        yield ['FCONST', [0, 1, 0], ['2147483648', '.' . str_repeat('9', 20)]];
        yield ['Op', $minimum, ['?']];
        yield ['Op', [], ['?' . str_repeat('~', 31)]];

        foreach (range(1, 127) as $byte) {
            $plain = str_replace("'", "''", chr($byte));
            $escaped = str_replace('\\', '\\\\', $plain);
            yield ['SCONST', [1, 0, 0, 1, $byte - 1], ["'" . $plain . "'"]];
            yield ['SCONST', [1, 1, 0, 1, $byte - 1], ["E'" . $escaped . "'"]];
            yield ['USCONST', [1, 0, 1, $byte - 1], ["U&'" . $escaped . "'"]];
        }
    }

    public function testContextualNamesRestrictsTheDomain(): void
    {
        $generator = (new DefinitionFactory())->create('pg-17.2')->lexemes;
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['POLICY_MODE']), 0, new ResolvedOutput(), null));
        self::assertNotNull($result);
        self::assertSame(['PERMISSIVE', 'RESTRICTIVE'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['POLICY_MODE']), 0, new ResolvedOutput(), 'unknown'));
        self::assertNotNull($invalid);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testContextualWordPreservesExplicitCase(): void
    {
        $generator = (new DefinitionFactory())->create('pg-17.2')->lexemes;
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['POLICY_MODE']), 0, new ResolvedOutput(), 'permissive'));
        self::assertNotNull($result);
        self::assertSame('permissive', [...$result->sequences()][0]->lexemes[0]->text);
    }

    public function testCreateIncludesEveryGrammarEncodingAndPartitionStrategyContextualName(): void
    {
        $generator = (new DefinitionFactory())->create('pg-17.2')->lexemes;
        $encoding = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['JSON_ENCODING']), 0, new ResolvedOutput()));
        $strategy = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['PARTITION_STRATEGY']), 0, new ResolvedOutput()));
        self::assertNotNull($encoding);
        self::assertNotNull($strategy);
        self::assertSame(['UTF8', 'UTF16', 'UTF32'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$encoding->sequences()]));
        self::assertSame(['LIST', 'RANGE', 'HASH'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$strategy->sequences()]));
    }

    public function testKeywordsKeepsSourceSpellingsBesideTheirTerminals(): void
    {
        $keywords = (new DefinitionFactory())->create('pg-17.2')->keywords;
        self::assertSame(['SELECT'], $keywords['SELECT']);
        self::assertSame(['INTEGER'], $keywords['INTEGER']);
        self::assertSame(['INT'], $keywords['INT_P']);
    }

    public function testKeywordLexemesLeavesUnclaimedNamesAvailableToOtherDomains(): void
    {
        $factory = new DefinitionFactory();
        $generator = $factory->create('pg-17.2')->lexemes;
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['UNDECLARED_TEST_TOKEN']), 0, new ResolvedOutput())));
        $result = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['SELECT']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame('SELECT', [...$result->sequences()][0]->lexemes[0]->text);
    }

    public function testKeywordsRejectsUnsupportedReleases(): void
    {
        $this->expectException(RuntimeException::class);
        (new DefinitionFactory())->create('unknown')->keywords;
    }
}
