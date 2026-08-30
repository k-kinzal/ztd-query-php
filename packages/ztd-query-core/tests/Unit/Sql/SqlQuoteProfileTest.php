<?php

declare(strict_types=1);

namespace Tests\Unit\Sql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Tests\Fake\FakeSqlLexerProfiles;
use ZtdQuery\Exception\InvalidDefinitionException;
use ZtdQuery\Sql\LexicalDelimiters;
use ZtdQuery\Sql\LexicalPattern;
use ZtdQuery\Sql\SqlQuoteProfile;
use ZtdQuery\Sql\SqlSymbolProfile;

#[CoversClass(SqlQuoteProfile::class)]
#[UsesClass(SqlSymbolProfile::class)]
#[UsesClass(LexicalDelimiters::class)]
#[UsesClass(LexicalPattern::class)]
final class SqlQuoteProfileTest extends TestCase
{
    public function testRefusesAQuoteThatSpellsNothing(): void
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('Identifier quote delimiters must not be empty.');

        FakeSqlLexerProfiles::quotes(identifierQuotePairs: ['' => '"']);
    }

    public function testRefusesADollarQuotePatternThatCannotBeRead(): void
    {
        $this->expectException(InvalidDefinitionException::class);

        FakeSqlLexerProfiles::quotes(dollarQuoteDelimiterPattern: '/[/');
    }

    public function testStringQuoteClosingAnswersWhatClosesAStringThatQuoteOpened(): void
    {
        $profile = FakeSqlLexerProfiles::quotes(stringQuotePairs: ['<<' => '>>']);

        self::assertSame(['>>', null], [$profile->stringQuoteClosing('<<'), $profile->stringQuoteClosing('"')]);
    }

    public function testIdentifierQuoteClosingAnswersWhatClosesANameThatQuoteOpened(): void
    {
        $profile = FakeSqlLexerProfiles::quotes(identifierQuotePairs: ['[' => ']']);

        self::assertSame([']', null], [$profile->identifierQuoteClosing('['), $profile->identifierQuoteClosing('`')]);
    }

    public function testUnquoteIdentifierReadsADoubledQuoteAsTheQuoteItself(): void
    {
        self::assertSame('a"b', FakeSqlLexerProfiles::quotes()->unquoteIdentifier('"a""b"'));
    }

    public function testUnquoteIdentifierLeavesANameThatWasNeverQuotedAsItStands(): void
    {
        self::assertSame('a', FakeSqlLexerProfiles::quotes()->unquoteIdentifier('a'));
    }

    public function testQuotedIdentifierValueAnswersNothingForANameThatWasNeverQuoted(): void
    {
        self::assertNull(FakeSqlLexerProfiles::quotes()->quotedIdentifierValue('a'));
    }

    public function testQuotedIdentifierValueAnswersNothingForANameThatWasNeverClosed(): void
    {
        self::assertNull(FakeSqlLexerProfiles::quotes()->quotedIdentifierValue('"a'));
    }

    public function testQuotedIdentifierValueReadsADoubledQuoteAsTheQuoteItself(): void
    {
        self::assertSame('a"b', FakeSqlLexerProfiles::quotes()->quotedIdentifierValue('"a""b"'));
    }

    public function testDollarQuoteDelimiterAtAnswersTheTagThatOpensARun(): void
    {
        $profile = FakeSqlLexerProfiles::quotes(dollarQuoteDelimiterPattern: '/^\$[a-z]*\$/');

        self::assertSame(['$t$', null], [$profile->dollarQuoteDelimiterAt('$t$a', 0), $profile->dollarQuoteDelimiterAt('a', 0)]);
    }

    public function testStringUsesBackslashEscapesWhereTheDialectSaysSoOfEveryString(): void
    {
        $profile = FakeSqlLexerProfiles::quotes(backslashEscapedStrings: true);

        self::assertTrue($profile->stringUsesBackslashEscapes("'a'", 0, FakeSqlLexerProfiles::symbols()));
    }

    public function testStringUsesBackslashEscapesOnlyWhereThePrefixIsNotTheTailOfAName(): void
    {
        $profile = FakeSqlLexerProfiles::quotes(backslashEscapedStringPrefixes: ['E']);
        $symbols = FakeSqlLexerProfiles::symbols();

        self::assertSame(
            [true, false, false],
            [
                $profile->stringUsesBackslashEscapes("E'a'", 1, $symbols),
                $profile->stringUsesBackslashEscapes("xE'a'", 2, $symbols),
                $profile->stringUsesBackslashEscapes("'a'", 0, $symbols),
            ],
        );
    }
}
