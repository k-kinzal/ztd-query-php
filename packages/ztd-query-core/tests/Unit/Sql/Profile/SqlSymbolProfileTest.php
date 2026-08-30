<?php

declare(strict_types=1);

namespace Tests\Unit\Sql\Profile;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Sql\LexicalPattern;
use ZtdQuery\Sql\Profile\SqlSymbolProfile;

#[CoversClass(SqlSymbolProfile::class)]
#[UsesClass(LexicalPattern::class)]
final class SqlSymbolProfileTest extends TestCase
{
    public function testRefusesAPatternThatCannotBeRead(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::symbols(numericLiteralPattern: '/[/');
    }

    public function testRefusesABracketDelimiterThatSpellsNothing(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Bracket delimiters must not be empty.');

        FakeSqlLexerProfiles::symbols(bracketPair: ['', ']']);
    }

    public function testRefusesANestingDelimiterThatSpellsNothing(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Nesting delimiters must not be empty.');

        FakeSqlLexerProfiles::symbols(nestingPair: ['(', '']);
    }

    public function testRefusesAStatementDelimiterThatIsNotOneCharacter(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Statement and list delimiters must be single characters.');

        FakeSqlLexerProfiles::symbols(statementDelimiter: ';;');
    }

    public function testNumberLengthAtMeasuresAsFarAsTheDialectSpellsANumber(): void
    {
        $profile = FakeSqlLexerProfiles::symbols(numericLiteralPattern: '/^[0-9]+/');

        self::assertSame([3, 0], [$profile->numberLengthAt('123x', 0), $profile->numberLengthAt('x', 0)]);
    }

    public function testIsIdentifierStartAnswersWhatMayOpenAName(): void
    {
        $profile = FakeSqlLexerProfiles::symbols();

        self::assertSame([true, false], [$profile->isIdentifierStart('a'), $profile->isIdentifierStart('1')]);
    }

    public function testIsIdentifierPartAnswersWhatMayCarryAName(): void
    {
        $profile = FakeSqlLexerProfiles::symbols();

        self::assertSame([true, false], [$profile->isIdentifierPart('1'), $profile->isIdentifierPart('-')]);
    }

    public function testIsBracketOpeningAnswersFalseWhereTheDialectBracketsNothing(): void
    {
        self::assertFalse(FakeSqlLexerProfiles::symbols()->isBracketOpening('['));
    }

    public function testIsBracketClosingAnswersWhatTheDialectClosesABracketWith(): void
    {
        $profile = FakeSqlLexerProfiles::symbols(bracketPair: ['[', ']']);

        self::assertSame([true, false], [$profile->isBracketClosing(']'), $profile->isBracketClosing('[')]);
    }

    public function testIsNestingOpeningAnswersWhatTheDialectNestsWith(): void
    {
        self::assertTrue(FakeSqlLexerProfiles::symbols()->isNestingOpening('('));
    }

    public function testIsNestingClosingAnswersWhatTheDialectClosesANestingWith(): void
    {
        self::assertTrue(FakeSqlLexerProfiles::symbols()->isNestingClosing(')'));
    }

    public function testIsStatementDelimiterAnswersWhatEndsAStatement(): void
    {
        self::assertTrue(FakeSqlLexerProfiles::symbols()->isStatementDelimiter(';'));
    }

    public function testListDelimiterAnswersWhatSeparatesListItems(): void
    {
        self::assertSame(',', FakeSqlLexerProfiles::symbols()->listDelimiter());
    }
}
