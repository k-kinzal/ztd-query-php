<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Output\SqlSerializer;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\Sqlite\Generation\Lexeme\DefinitionFactory;

#[CoversClass(DefinitionFactory::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SqlSerializer::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\GenerationPlan::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator::class)]
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
#[UsesClass(\SqlFaker\MySql\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ValueChoices::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinModifiers::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\WindowNameLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\IdentifierDomain::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Value\QuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\RepeatDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\WordDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Resource\SqlVersionRegistry::class)]
#[UsesClass(\SqlFaker\Grammar\SqlVersion::class)]
#[UsesClass(\SqlFaker\Grammar\Choice\BytePlanCompiler::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\LexicalDefinition::class)]
final class DefinitionFactoryTest extends TestCase
{
    public function testCreateCombinesLexicalOutputAndBoundaryDecisions(): void
    {
        $pipeline = (new DefinitionFactory())->create('sqlite-3.47.2')->pipeline;
        $result = $pipeline->generate(TerminalSequence::fromNames(['INTEGER', 'SEMI']), null, static fn (int $count): int => 0);
        self::assertSame('1 ;', (new SqlSerializer())->serialize($result->pieces()));
    }

    public function testLexemesRejectsAnUnreviewedVersion(): void
    {
        $this->expectException(RuntimeException::class);
        (new DefinitionFactory())->create('future-version')->lexemes;
    }

    public function testSymbolsKeepMultiCharacterOperatorsIndivisible(): void
    {
        $result = (new DefinitionFactory())->create('sqlite-3.47.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['CONCAT']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame('||', [...$result->sequences()][0]->lexemes[0]->text);
    }

    public function testStrictTypesCoversTheSourceTypeTable(): void
    {
        $result = (new DefinitionFactory())->create('sqlite-3.47.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['STRICT_COLUMN_TYPE']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame(['ANY', 'BLOB', 'INT', 'INTEGER', 'REAL', 'TEXT'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerGeneratedStorage')]
    public function testLexemesRestrictsGeneratedStorageToTheBuildSourceNames(?string $spelling, array $expected): void
    {
        $result = (new DefinitionFactory())->create('sqlite-3.47.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['GENERATED_STORAGE']), 0, new ResolvedOutput(), $spelling));
        self::assertNotNull($result);
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{?string, list<string>}>
     */
    public static function providerGeneratedStorage(): iterable
    {
        yield [null, ['VIRTUAL', 'STORED']];
        yield ['virtual', ['virtual']];
        yield ['Stored', ['Stored']];
        yield ['name', []];
        yield ['"VIRTUAL"', []];
        yield ['[STORED]', []];
    }

    public function testValuesLeavesUnknownTerminalsUnclaimed(): void
    {
        self::assertNull((new DefinitionFactory())->create('sqlite-3.47.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames(['UNKNOWN']), 0, new ResolvedOutput())));
    }

    public function testNamesChecksSourceDelimiterOrValueBoundariesValue(): void
    {
        $generator = (new DefinitionFactory())->create('sqlite-3.47.2')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ID']), 0, new ResolvedOutput(), '"a""b"'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['ID']), 0, new ResolvedOutput(), '"unclosed'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('"a""b"', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testStringsChecksSourceDelimiterOrValueBoundariesValue(): void
    {
        $generator = (new DefinitionFactory())->create('sqlite-3.47.2')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['BLOB']), 0, new ResolvedOutput(), "X'00'"));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['BLOB']), 0, new ResolvedOutput(), "X'0'"));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame("X'00'", [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    public function testNumbersChecksSourceDelimiterOrValueBoundariesValue(): void
    {
        $generator = (new DefinitionFactory())->create('sqlite-3.47.2')->lexemes;
        $valid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['QNUMBER']), 0, new ResolvedOutput(), '1_2'));
        $invalid = $generator->generate(new LexemeInput(TerminalSequence::fromNames(['QNUMBER']), 0, new ResolvedOutput(), '1__2'));
        self::assertNotNull($valid);
        self::assertNotNull($invalid);
        self::assertSame('1_2', [...$valid->sequences()][0]->lexemes[0]->text);
        self::assertSame([], [...$invalid->sequences()]);
    }

    #[DataProvider('providerValueTokensValue')]
    public function testCreateOffersACompleteCandidateForEverySourceValueTokenValue(string $terminal): void
    {
        $result = (new DefinitionFactory())->create('sqlite-3.47.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertNotEmpty([...$result->sequences()]);
    }

    /**
     * @return list<array{string}>
     */
    public static function providerValueTokensValue(): array
    {
        return [['ID'], ['id'], ['idj'], ['ANY'], ['VARIABLE'], ['INTEGER'], ['number'], ['FLOAT'], ['QNUMBER'], ['STRING'], ['ids'], ['BLOB']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerLexicalFormsValue')]
    public function testCreateRetainsValidSourceSpellingsAndRejectsMalformedOnesValue(string $terminal, string $spelling, array $expected): void
    {
        $result = (new DefinitionFactory())->create('sqlite-3.47.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), $spelling));
        self::assertNotNull($result);
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return list<array{string, string, list<string>}>
     */
    public static function providerLexicalFormsValue(): array
    {
        return [
            ['STRING', "'ordinary'", ["'ordinary'"]],
            ['STRING', "'with\0nul'", []],
            ['ids', "'ordinary'", ["'ordinary'"]],
            ['ids', "'with\0nul'", []],
            ['ID', '[a b]', ['[a b]']],
            ['ID', '[unclosed', []],
            ['id', '`a``b`', ['`a``b`']],
            ['id', '`unclosed', []],
            ['idj', '"a""b"', ['"a""b"']],
            ['idj', '"unclosed', []],
            ['ANY', 'alpha_7', ['alpha_7']],
            ['ANY', 'bad name', []],
            ['VARIABLE', '?23', ['?23']],
            ['VARIABLE', '?abc', []],
            ['INTEGER', '0XFE', ['0XFE']],
            ['INTEGER', '0xGG', []],
            ['number', '12', ['12']],
            ['number', '-12', []],
            ['FLOAT', '.75e-2', ['.75e-2']],
            ['FLOAT', '.75e', []],
            ['QNUMBER', '12_345_6', ['12_345_6']],
            ['QNUMBER', '12__3', []],
            ['ids', '\'a\'\'b\'', ['\'a\'\'b\'']],
            ['ids', '\'unclosed', []],
            ['BLOB', 'x\'01af\'', ['x\'01af\'']],
            ['BLOB', 'X\'abc\'', []],
        ];
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
        $result = (new DefinitionFactory())->create('sqlite-3.47.2')->lexemes->generate(new LexemeInput(TerminalSequence::fromNames([$terminal]), 0, new ResolvedOutput(), values: $values));
        self::assertNotNull($result);
        self::assertSame($expected, array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }

    /**
     * @return iterable<array{string, list<int>, list<string>}>
     */
    public static function providerConstructedBoundariesValue(): iterable
    {
        $minimum = [1, ...array_fill(0, 512, 0)];

        foreach (['ID', 'id', 'idj', 'ANY'] as $terminal) {
            yield [$terminal, $minimum, ['"a"']];
            yield [$terminal, [], ['[' . str_repeat('猫', 64) . ']']];
        }
        yield ['VARIABLE', $minimum, ['?']];
        yield ['VARIABLE', [], ['$v' . str_repeat('$', 63)]];
        yield ['VARIABLE', [1, 1], ['?32766']];
        yield ['INTEGER', $minimum, ['0']];
        yield ['INTEGER', [], ['0X' . str_repeat('F', 16)]];
        yield ['number', $minimum, ['0']];
        yield ['number', [], [str_repeat('9', 19)]];
        yield ['FLOAT', $minimum, ['0.']];
        yield ['FLOAT', [], ['.' . str_repeat('9', 19) . 'E-999']];
        yield ['FLOAT', [1, 0], ['.' . str_repeat('9', 19)]];
        yield ['QNUMBER', $minimum, ['0_0']];
        yield ['QNUMBER', [], [str_repeat('9', 19) . str_repeat('_' . str_repeat('9', 19), 8)]];
        foreach (['STRING', 'ids'] as $terminal) {
            yield [$terminal, $minimum, ["''"]];
            yield [$terminal, [], ["'" . str_repeat('😀', 255) . "'"]];
        }
        yield ['BLOB', $minimum, ["X''"]];
        yield ['BLOB', [], ["x'" . str_repeat('F', 254) . "'"]];

        foreach (range(1, 127) as $byte) {
            $encoded = str_replace("'", "''", chr($byte));
            yield ['STRING', [1, 0, 1, $byte - 1], ["'" . $encoded . "'"]];
            yield ['ids', [1, 0, 1, $byte - 1], ["'" . $encoded . "'"]];
        }
    }

    public function testKeywordsKeepsSourceSpellingsBesideTheirTerminals(): void
    {
        $keywords = (new DefinitionFactory())->create('sqlite-3.47.2')->keywords;
        self::assertSame(['SELECT'], $keywords['SELECT']);
        self::assertSame(['CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP'], $keywords['CTIME_KW']);
    }

    public function testKeywordLexemesLeavesUnclaimedNamesAvailableToOtherDomains(): void
    {
        $factory = new DefinitionFactory();
        $generator = $factory->create('sqlite-3.47.2')->lexemes;
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['UNKNOWN']), 0, new ResolvedOutput())));
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
