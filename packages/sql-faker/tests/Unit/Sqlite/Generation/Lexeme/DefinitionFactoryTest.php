<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
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
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\PatternLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\RegisteredLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\CandidateResolver::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\OutputPart::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Output\ReverseLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\CombinedSpacingRule::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\LexemeBoundary::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\KeywordDefinitions::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\ValueDefinitions::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\GenerationPlan::class)]
#[UsesClass(\SqlFaker\Grammar\Derivation\ProductionPattern::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\LexicalException::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\JoinModifiers::class)]
#[UsesClass(\SqlFaker\Sqlite\Generation\Lexeme\WindowNameLexemeGenerator::class)]
final class DefinitionFactoryTest extends TestCase
{
    public function testCreateCombinesLexicalOutputAndBoundaryDecisions(): void
    {
        $pipeline = (new DefinitionFactory())->create('sqlite-3.47.2', []);
        $result = $pipeline->generate(TerminalSequence::fromNames(['INTEGER', 'SEMI']), null, static fn (int $count): int => 0);
        self::assertSame('1 ;', (new SqlSerializer())->serialize($result->pieces()));
    }

    public function testLexemesLeavesAnUnreviewedVersionUnclaimed(): void
    {
        $generator = (new DefinitionFactory())->lexemes('future-version', []);
        self::assertNull($generator->generate(new LexemeInput(TerminalSequence::fromNames(['INTEGER']), 0, new ResolvedOutput())));
    }

    public function testSymbolsKeepMultiCharacterOperatorsIndivisible(): void
    {
        $result = (new DefinitionFactory())->symbols()->generate(new LexemeInput(TerminalSequence::fromNames(['CONCAT']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame('||', [...$result->sequences()][0]->lexemes[0]->text);
    }

    public function testStrictTypesCoversTheSourceTypeTable(): void
    {
        $result = (new DefinitionFactory())->strictTypes()->generate(new LexemeInput(TerminalSequence::fromNames(['STRICT_COLUMN_TYPE']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame(['ANY', 'BLOB', 'INT', 'INTEGER', 'REAL', 'TEXT'], array_map(static fn ($candidate): string => $candidate->lexemes[0]->text, [...$result->sequences()]));
    }
}
