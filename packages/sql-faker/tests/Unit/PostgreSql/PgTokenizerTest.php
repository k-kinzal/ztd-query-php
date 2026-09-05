<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\LexicalException;
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
        yield 'named argument prefix in a longer operator' => ['=>@', ['Op']];
        yield 'inequality prefix in a longer operator' => ['<>*', ['Op']];
        yield 'comparison prefix in a longer operator' => ['<=?', ['Op']];
        yield 'SQL operator before a sign' => ['=-1', ['=', '-', 'ICONST']];
        yield 'comparison before a sign' => ['<=+1', ['LESS_EQUALS', '+', 'ICONST']];
        yield 'all signs are separate operators' => ['++1', ['+', '+', 'ICONST']];
        yield 'non-SQL operator retains a trailing sign' => ['?-1', ['Op', 'ICONST']];
        yield 'named argument stops before a comment' => ['=>/* note */1', ['EQUALS_GREATER', 'ICONST']];
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
}
