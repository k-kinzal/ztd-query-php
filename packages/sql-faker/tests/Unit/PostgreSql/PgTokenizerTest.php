<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalException;
use SqlFaker\PostgreSql\PgLookahead;
use SqlFaker\PostgreSql\PgTokenizer;

#[CoversClass(PgTokenizer::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(PgLookahead::class)]
final class PgTokenizerTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerSql')]
    public function testTokenizeReadsTextAsTheServerWould(string $sql, array $expected): void
    {
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT', 'NOT' => 'NOT'], new PgLookahead([]));

        self::assertSame($expected, $tokenizer->tokenize($sql));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerSql(): iterable
    {
        yield 'nothing' => ['', []];
        yield 'keyword' => ['SELECT', ['SELECT']];
        yield 'keyword regardless of case' => ['select', ['SELECT']];
        yield 'identifier' => ['users', ['IDENT']];
        yield 'quoted identifier' => ['"users"', ['IDENT']];
        yield 'unicode quoted identifier' => ['U&"users"', ['IDENT']];
        yield 'string literal' => ["'text'", ['SCONST']];
        yield 'unicode string literal' => ["U&'text'", ['SCONST']];
        yield 'escaped string literal' => ["E'text'", ['SCONST']];
        yield 'bit string literal' => ["B'01'", ['BCONST']];
        yield 'hex string literal' => ["X'FF'", ['XCONST']];
        yield 'dollar quoted string' => ['$$text$$', ['SCONST']];
        yield 'tagged dollar quoted string' => ['$tag$text$tag$', ['SCONST']];
        yield 'positional parameter' => ['$1', ['PARAM']];
        yield 'integer' => ['42', ['ICONST']];
        yield 'float' => ['1.5', ['FCONST']];
        yield 'exponent float' => ['1e3', ['FCONST']];
        yield 'typecast' => ['::', ['TYPECAST']];
        yield 'range' => ['..', ['DOT_DOT']];
        yield 'assignment' => [':=', ['COLON_EQUALS']];
        yield 'named argument' => ['=>', ['EQUALS_GREATER']];
        yield 'inequality' => ['<>', ['NOT_EQUALS']];
        yield 'user operator' => ['?|', ['Op']];
        yield 'punctuation' => ['(', ['(']];
        yield 'line comment is trivia' => ["SELECT -- note\nusers", ['SELECT', 'IDENT']];
        yield 'block comment is trivia' => ['SELECT /* note */ users', ['SELECT', 'IDENT']];
        yield 'block comments nest' => ['SELECT /* a /* b */ c */ users', ['SELECT', 'IDENT']];
        yield 'operator stops before a comment' => ["+-- note\nusers", ['+', 'IDENT']];
    }

    public function testTokenizeAppliesTheLookaheadSubstitution(): void
    {
        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);
        $tokenizer = new PgTokenizer(['NOT' => 'NOT', 'NULL' => 'NULL_P'], $lookahead);

        self::assertSame(['NOT_LA', 'NULL_P'], $tokenizer->tokenize('NOT NULL'));
    }

    public function testTokenizeReportsTextItCannotRead(): void
    {
        $tokenizer = new PgTokenizer([], new PgLookahead([]));

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported PostgreSQL lexical input at offset 0:');

        $tokenizer->tokenize('\\');
    }

    public function testTokenizeReportsAnUnterminatedDollarQuotedString(): void
    {
        $tokenizer = new PgTokenizer([], new PgLookahead([]));

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated PostgreSQL dollar-quoted string.');

        $tokenizer->tokenize('$$text');
    }

    public function testQuotedTokenAtLeavesAnythingThatOpensNoQuote(): void
    {
        $offset = 0;

        self::assertNull((new PgTokenizer([], new PgLookahead([])))->quotedTokenAt('users', $offset));
        self::assertSame(0, $offset);
    }

    public function testDollarTokenAtLeavesADollarThatOpensNeither(): void
    {
        $offset = 0;

        self::assertNull((new PgTokenizer([], new PgLookahead([])))->dollarTokenAt('users', $offset));
    }

    public function testNumericTokenAtLeavesTextThatIsNoNumber(): void
    {
        $offset = 0;

        self::assertNull((new PgTokenizer([], new PgLookahead([])))->numericTokenAt('users', $offset));
    }

    public function testWordTokenAtLeavesTextThatIsNoWord(): void
    {
        $offset = 0;

        self::assertNull((new PgTokenizer([], new PgLookahead([])))->wordTokenAt('42', $offset));
    }

    public function testOperatorTokenAtLeavesTextThatIsNoOperator(): void
    {
        $offset = 0;

        self::assertNull((new PgTokenizer([], new PgLookahead([])))->operatorTokenAt('users', $offset));
    }

    public function testTokenAtReadsTheFirstTokenAndAdvancesPastIt(): void
    {
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], new PgLookahead([]));
        $offset = 0;

        self::assertSame('SELECT', $tokenizer->tokenAt('SELECT users', $offset));
        self::assertSame(6, $offset);
    }

    public function testSkipTriviaReportsWhenNothingWasSkipped(): void
    {
        $offset = 0;

        self::assertFalse((new PgTokenizer([], new PgLookahead([])))->skipTrivia('SELECT', $offset));
    }

    public function testSkipTriviaReportsAnUnterminatedBlockComment(): void
    {
        $offset = 0;

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated PostgreSQL block comment.');

        (new PgTokenizer([], new PgLookahead([])))->skipTrivia('/* never closed', $offset);
    }

    public function testSkipQuotedTreatsADoubledQuoteAsAnEscape(): void
    {
        $offset = 0;

        (new PgTokenizer([], new PgLookahead([])))->skipQuoted("'a''b' rest", $offset, "'");

        self::assertSame(6, $offset);
    }

    public function testSkipQuotedReportsARunThatNeverCloses(): void
    {
        $offset = 0;

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated PostgreSQL quoted token');

        (new PgTokenizer([], new PgLookahead([])))->skipQuoted("'abc", $offset, "'");
    }

    public function testOperatorAtTakesTheLongestRunOfOperatorCharacters(): void
    {
        self::assertSame(['+*', 'Op'], (new PgTokenizer([], new PgLookahead([])))->operatorAt('+*x', 0));
    }

    public function testOperatorAtStopsAtTheEndOfTheText(): void
    {
        self::assertSame(['+*', 'Op'], (new PgTokenizer([], new PgLookahead([])))->operatorAt('+*', 0));
    }

    public function testOperatorAtStopsWhereACommentOpens(): void
    {
        self::assertSame(['+', '+'], (new PgTokenizer([], new PgLookahead([])))->operatorAt('+--x', 0));
    }

    public function testOperatorAtStopsWhereABlockCommentOpens(): void
    {
        self::assertSame(['+', '+'], (new PgTokenizer([], new PgLookahead([])))->operatorAt('+/*x', 0));
    }

    public function testOperatorAtReportsPunctuationAsItself(): void
    {
        self::assertSame(['(', '('], (new PgTokenizer([], new PgLookahead([])))->operatorAt('(x', 0));
    }

    public function testOperatorAtReportsNothingForAWord(): void
    {
        self::assertNull((new PgTokenizer([], new PgLookahead([])))->operatorAt('users', 0));
    }

    public function testFixedOperatorNamesTheOperatorsThatHaveTokens(): void
    {
        $tokenizer = new PgTokenizer([], new PgLookahead([]));

        self::assertSame('TYPECAST', $tokenizer->fixedOperator('::'));
        self::assertSame('NOT_EQUALS', $tokenizer->fixedOperator('!='));
        self::assertNull($tokenizer->fixedOperator('?|'));
    }

    public function testIsPunctuationAcceptsASingleGrammarCharacter(): void
    {
        $tokenizer = new PgTokenizer([], new PgLookahead([]));

        self::assertTrue($tokenizer->isPunctuation('('));
        self::assertFalse($tokenizer->isPunctuation('a'));
        self::assertFalse($tokenizer->isPunctuation('::'));
    }
    #[DataProvider('providerDollarToken')]
    public function testDollarTokenAtReadsBothOfTheThingsADollarStarts(string $sql, ?string $token, int $consumed): void
    {
        $offset = 0;
        $read = (new PgTokenizer([], new PgLookahead([])))->dollarTokenAt($sql, $offset);

        self::assertSame([$token, $consumed], [$read, $offset]);
    }

    /**
     * @return iterable<string, array{string, string|null, int}>
     */
    public static function providerDollarToken(): iterable
    {
        yield 'an untagged dollar-quoted string' => ['$$body$$x', 'SCONST', 8];
        yield 'a tagged dollar-quoted string' => ['$tag$body$tag$x', 'SCONST', 14];
        yield 'a parameter marker' => ['$12,', 'PARAM', 3];
        yield 'a dollar that starts neither' => ['$0', null, 0];
    }

    public function testDollarTokenAtRefusesADollarQuotedStringThatNeverCloses(): void
    {
        $offset = 0;

        $this->expectExceptionMessage('Unterminated PostgreSQL dollar-quoted string');

        (new PgTokenizer([], new PgLookahead([])))->dollarTokenAt('$$body', $offset);
    }

    #[DataProvider('providerNumericLiteral')]
    public function testNumericTokenAtReadsEverySpellingPostgresAcceptsForANumber(string $sql, string $token, int $consumed): void
    {
        $offset = 0;
        $read = (new PgTokenizer([], new PgLookahead([])))->numericTokenAt($sql, $offset);

        self::assertSame([$token, $consumed], [$read, $offset]);
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function providerNumericLiteral(): iterable
    {
        yield 'a whole number' => ['42x', 'ICONST', 2];
        yield 'a decimal with digits either side' => ['1.5x', 'FCONST', 3];
        yield 'a decimal with nothing after the point' => ['1.x', 'FCONST', 2];
        yield 'a decimal with nothing before the point' => ['.5x', 'FCONST', 2];
        yield 'a float with a lower-case exponent' => ['1e3x', 'FCONST', 3];
        yield 'a float with an upper-case exponent' => ['1E3x', 'FCONST', 3];
        yield 'a float with a signed exponent' => ['1.5e-3x', 'FCONST', 6];
    }

    #[DataProvider('providerWord')]
    public function testWordTokenAtReadsAKeywordHoweverItIsCased(string $sql, string $token): void
    {
        $offset = 0;
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], new PgLookahead([]));

        self::assertSame($token, $tokenizer->wordTokenAt($sql, $offset));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerWord(): iterable
    {
        yield 'a keyword in upper case' => ['SELECT', 'SELECT'];
        yield 'a keyword in lower case' => ['select', 'SELECT'];
        yield 'a keyword in mixed case' => ['SeLeCt', 'SELECT'];
        yield 'a word nothing else names' => ['users', 'IDENT'];
        yield 'a word starting with an underscore' => ['_users', 'IDENT'];
    }

    public function testWordTokenAtMovesPastExactlyTheWordItRead(): void
    {
        $offset = 0;
        (new PgTokenizer([], new PgLookahead([])))->wordTokenAt('users, id', $offset);

        self::assertSame(5, $offset);
    }

    #[DataProvider('providerTrivia')]
    public function testSkipTriviaMovesPastEveryKindOfSeparator(string $sql, bool $skipped, int $consumed): void
    {
        $offset = 0;
        $read = (new PgTokenizer([], new PgLookahead([])))->skipTrivia($sql, $offset);

        self::assertSame([$skipped, $consumed], [$read, $offset]);
    }

    /**
     * @return iterable<string, array{string, bool, int}>
     */
    public static function providerTrivia(): iterable
    {
        yield 'whitespace' => ["  \n x", true, 4];
        yield 'a line comment ending at a newline' => ["-- note\nx", true, 8];
        yield 'a line comment ending at the text' => ['-- note', true, 7];
        yield 'a block comment' => ['/* note */x', true, 10];
        yield 'a block comment holding another' => ['/* a /* b */ c */x', true, 17];
        yield 'text that is no separator at all' => ['x', false, 0];
    }

    public function testSkipTriviaRefusesABlockCommentThatNeverCloses(): void
    {
        $offset = 0;

        $this->expectExceptionMessage('Unterminated PostgreSQL block comment');

        (new PgTokenizer([], new PgLookahead([])))->skipTrivia('/* note', $offset);
    }
}
