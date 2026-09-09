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
}
