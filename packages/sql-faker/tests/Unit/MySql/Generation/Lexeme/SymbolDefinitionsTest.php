<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql\Generation\Lexeme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Generation\Lexeme\LexemeInput;
use SqlFaker\Grammar\Generation\Output\ResolvedOutput;
use SqlFaker\Grammar\Generation\Token\TerminalSequence;
use SqlFaker\MySql\Generation\Lexeme\SymbolDefinitions;

#[CoversClass(SymbolDefinitions::class)]
#[UsesClass(LexemeInput::class)]
#[UsesClass(ResolvedOutput::class)]
#[UsesClass(TerminalSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\ChoiceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\FixedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\Lexeme::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeCandidates::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\LexemeSequence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\MatchingLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Lexeme\SequenceLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\TerminalOccurrence::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionCase::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Version\VersionedLexemeGenerator::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Spacing\SpacingConstraint::class)]
#[UsesClass(\SqlFaker\Grammar\Generation\Token\ProductionOccurrence::class)]
final class SymbolDefinitionsTest extends TestCase
{
    public function testCreateKeepsTheCompleteDeclaredOutput(): void
    {
        $result = (new SymbolDefinitions())->create('mysql-8.4.7')->generate(new LexemeInput(TerminalSequence::fromNames(['SET_VAR']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame(':=', implode(' ', array_map(static fn ($lexeme): string => $lexeme->text, [...$result->sequences()][0]->lexemes)));
    }

    public function testJsonKeepsTheCompleteDeclaredOutput(): void
    {
        $result = (new SymbolDefinitions())->json('mysql-5.7.44')->generate(new LexemeInput(TerminalSequence::fromNames(['JSON_SEPARATOR_SYM']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame('->', implode(' ', array_map(static fn ($lexeme): string => $lexeme->text, [...$result->sequences()][0]->lexemes)));
    }

    public function testPhrasesKeepsTheCompleteDeclaredOutput(): void
    {
        $result = (new SymbolDefinitions())->phrases('mysql-8.4.7')->generate(new LexemeInput(TerminalSequence::fromNames(['WITH_ROLLUP_SYM']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame('WITH ROLLUP', implode(' ', array_map(static fn ($lexeme): string => $lexeme->text, [...$result->sequences()][0]->lexemes)));
    }

    public function testSelectorsKeepsTheCompleteDeclaredOutput(): void
    {
        $result = (new SymbolDefinitions())->selectors('mysql-8.4.7')->generate(new LexemeInput(TerminalSequence::fromNames(['GRAMMAR_SELECTOR_EXPR']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        self::assertSame('', implode(' ', array_map(static fn ($lexeme): string => $lexeme->text, [...$result->sequences()][0]->lexemes)));
    }

    public function testPhraseKeepsBothWordsInTheSameOriginalOccurrence(): void
    {
        $result = (new SymbolDefinitions())->phrase('ROLLUP')->generate(new LexemeInput(TerminalSequence::fromNames(['WITH_ROLLUP_SYM']), 0, new ResolvedOutput()));
        self::assertNotNull($result);
        $lexemes = [...$result->sequences()][0]->lexemes;
        self::assertSame($lexemes[0]->origin, $lexemes[1]->origin);
        self::assertSame($lexemes[0]->phrase, $lexemes[1]->phrase);
    }

    public function testNonOutputDoesNotMisclassifyAStatementKeywordAsAMarker(): void
    {
        $markers = (new SymbolDefinitions())->nonOutput();
        self::assertContains('END_OF_INPUT', $markers);
        self::assertContains('GRAMMAR_SELECTOR_EXPR', $markers);
        self::assertNotContains('SELECT_SYM', $markers);
    }
}
