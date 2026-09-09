<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Output\SqlSerializer;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\DefinitionFactory;

#[CoversClass(DefinitionFactory::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(SqlSerializer::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\IntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\SequenceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CommonKeywordDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ContextualValueDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\DollarStringDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\KeywordDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\KeywordLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\SymbolDefinitions::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ValueDefinitions::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\GenerationPlan::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\KeywordPhraseSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\CloneAddressSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\FunctionSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\QualifiedNameSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Spacing\VariableSpacingRule::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\BoundedIntegerLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\ReplicationTablePatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\SizeNumberLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\FactorLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\PrecisionLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\CharacterDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\ChoiceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\IntegerDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\SequenceDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Value\DollarQuotedDomain::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\BoundaryCompletion::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetLexemeGenerator::class)]
#[UsesClass(\SqlFaker\MySql\Generation\Lexeme\CharsetValueLexemeGenerator::class)]
final class DefinitionFactoryTest extends TestCase
{
    public function testCreateCombinesLexicalOutputAndBoundaryDecisions(): void
    {
        $pipeline = (new DefinitionFactory())->create('mysql-8.4.7', [], []);
        $result = $pipeline->generate(TerminalSequence::fromNames(['NUM', 'END_OF_INPUT']), null, static fn (int $count): int => 0);
        self::assertSame('1', (new SqlSerializer())->serialize($result->pieces()));
    }

    public function testLexemesLeavesAnUnreviewedVersionUnclaimed(): void
    {
        $generator = (new DefinitionFactory())->lexemes('future-version', [], []);
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['NUM']), 0, new ResolvedOutput())));
    }

    #[DataProvider('providerKeyBlockSizes')]
    public function testLexemesUsesTheTwoByteKeyBlockSizeDomain(string $version, string $value, bool $valid): void
    {
        $generator = (new DefinitionFactory())->lexemes($version, [], []);
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
        $generator = (new DefinitionFactory())->lexemes('mysql-8.4.7', [], []);
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

}
