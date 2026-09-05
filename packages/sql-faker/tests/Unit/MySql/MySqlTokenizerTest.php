<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\MySql\MySqlTokenizer;

#[CoversClass(MySqlTokenizer::class)]
#[UsesClass(LexicalException::class)]
final class MySqlTokenizerTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerSql')]
    public function testTokenizeReadsTextAsTheServerWould(string $sql, array $expected): void
    {
        $tokenizer = new MySqlTokenizer(
            ['SELECT' => 'SELECT_SYM', 'WITH' => 'WITH', 'ROLLUP' => 'ROLLUP_SYM', '<=>' => 'EQUAL_SYM'],
            ['COUNT' => 'COUNT_SYM'],
            true,
        );

        self::assertSame($expected, $tokenizer->tokenize($sql));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerSql(): iterable
    {
        yield 'nothing' => ['', []];
        yield 'keyword' => ['SELECT', ['SELECT_SYM']];
        yield 'keyword regardless of case' => ['select', ['SELECT_SYM']];
        yield 'identifier' => ['users', ['IDENT']];
        yield 'quoted identifier' => ['`users`', ['IDENT_QUOTED']];
        yield 'string literal' => ["'text'", ['TEXT_STRING']];
        yield 'parameter marker' => ['?', ['PARAM_MARKER']];
        yield 'charset introducer' => ['_utf8mb4', ['UNDERSCORE_CHARSET']];
        yield 'other underscore word' => ['_other', ['IDENT']];
        yield 'hex in 0x form' => ['0xFF', ['HEX_NUM']];
        yield 'hex in quoted form' => ["X'FF'", ['HEX_NUM']];
        yield 'binary in 0b form' => ['0b01', ['BIN_NUM']];
        yield 'binary in quoted form' => ["B'01'", ['BIN_NUM']];
        yield 'national string' => ["N'text'", ['NCHAR_STRING']];
        yield 'decimal' => ['1.5', ['DECIMAL_NUM']];
        yield 'float' => ['1.5e3', ['FLOAT_NUM']];
        yield 'operator' => ['<=>', ['EQUAL_SYM']];
        yield 'punctuation' => ['(', ['(']];
        yield 'function before a parenthesis' => ['COUNT(', ['COUNT_SYM', '(']];
        yield 'function name used as an identifier' => ['COUNT ', ['IDENT']];
        yield 'user variable' => ['@name', ['@', 'LEX_HOSTNAME']];
        yield 'with rollup is one token' => ['WITH ROLLUP', ['WITH_ROLLUP_SYM']];
        yield 'dollar quoted string' => ['$$text$$', ['DOLLAR_QUOTED_STRING_SYM']];
        yield 'whitespace separates nothing' => ['  SELECT   users  ', ['SELECT_SYM', 'IDENT']];
        yield 'block comment is trivia' => ['SELECT /* note */ users', ['SELECT_SYM', 'IDENT']];
        yield 'hash comment is trivia' => ["SELECT # note\nusers", ['SELECT_SYM', 'IDENT']];
        yield 'dash comment is trivia' => ["SELECT -- note\nusers", ['SELECT_SYM', 'IDENT']];
    }

    public function testTokenizeRefusesADollarQuotedStringWhenTheBuildDoesNot(): void
    {
        $tokenizer = new MySqlTokenizer([], [], false);

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported MySQL lexical input at offset 0:');

        $tokenizer->tokenize('$$text$$');
    }

    public function testTokenizeReportsTextItCannotRead(): void
    {
        $tokenizer = new MySqlTokenizer([], [], true);

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported MySQL lexical input at offset 0:');

        $tokenizer->tokenize('\\');
    }

    public function testTokenizeReportsAnUnterminatedDollarQuotedString(): void
    {
        $tokenizer = new MySqlTokenizer([], [], true);

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated MySQL dollar-quoted string.');

        $tokenizer->tokenize('$$text');
    }

    public function testQuotedTokenAtLeavesAnythingThatOpensNoQuote(): void
    {
        $offset = 0;

        self::assertNull((new MySqlTokenizer([], [], true))->quotedTokenAt('users', $offset));
        self::assertSame(0, $offset);
    }

    public function testMarkerTokenAtReadsAHostnameOnlyAfterASigil(): void
    {
        $offset = 0;

        self::assertSame('LEX_HOSTNAME', (new MySqlTokenizer([], [], true))->markerTokenAt('localhost', $offset, ['@']));
        self::assertSame(9, $offset);
    }

    public function testMarkerTokenAtLeavesTheSameCharactersAloneWithoutASigil(): void
    {
        $offset = 0;

        self::assertNull((new MySqlTokenizer([], [], true))->markerTokenAt('localhost', $offset, []));
        self::assertSame(0, $offset);
    }

    public function testNumericTokenAtLeavesTextThatIsNoNumber(): void
    {
        $offset = 0;

        self::assertNull((new MySqlTokenizer([], [], true))->numericTokenAt('users', $offset));
    }

    public function testWordTokenAtLeavesTextThatIsNoWord(): void
    {
        $offset = 0;

        self::assertNull((new MySqlTokenizer([], [], true))->wordTokenAt('123', $offset));
    }

    public function testOperatorTokenAtLeavesTextThatIsNoOperator(): void
    {
        $offset = 0;

        self::assertNull((new MySqlTokenizer([], [], true))->operatorTokenAt('users', $offset));
    }

    public function testTokenAtReadsTheFirstTokenAndAdvancesPastIt(): void
    {
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $offset = 0;

        self::assertSame('SELECT_SYM', $tokenizer->tokenAt('SELECT users', $offset, []));
        self::assertSame(6, $offset);
    }

    public function testSkipTriviaReportsWhenNothingWasSkipped(): void
    {
        $offset = 0;

        self::assertFalse((new MySqlTokenizer([], [], true))->skipTrivia('SELECT', $offset));
        self::assertSame(0, $offset);
    }

    public function testSkipTriviaReportsAnUnterminatedBlockComment(): void
    {
        $offset = 0;

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated MySQL block comment.');

        (new MySqlTokenizer([], [], true))->skipTrivia('/* never closed', $offset);
    }

    public function testSkipQuotedTreatsADoubledQuoteAsAnEscape(): void
    {
        $offset = 0;

        (new MySqlTokenizer([], [], true))->skipQuoted("'a''b' rest", $offset, "'");

        self::assertSame(6, $offset);
    }

    public function testSkipQuotedReportsARunThatNeverCloses(): void
    {
        $offset = 0;

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated MySQL quoted token');

        (new MySqlTokenizer([], [], true))->skipQuoted("'abc", $offset, "'");
    }

    #[DataProvider('providerInteger')]
    public function testIntegerTokenWidensByMagnitude(string $digits, string $expected): void
    {
        self::assertSame($expected, (new MySqlTokenizer([], [], true))->integerToken($digits));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInteger(): iterable
    {
        yield 'zero' => ['0', 'NUM'];
        yield 'leading zeroes do not widen it' => ['0000000000000000001', 'NUM'];
        yield 'the largest NUM' => ['2147483647', 'NUM'];
        yield 'one past the largest NUM' => ['2147483648', 'LONG_NUM'];
        yield 'the largest LONG_NUM' => ['9223372036854775807', 'LONG_NUM'];
        yield 'one past the largest LONG_NUM' => ['9223372036854775808', 'ULONGLONG_NUM'];
        yield 'wider than any integer' => ['18446744073709551615', 'ULONGLONG_NUM'];
    }

    public function testOperatorAtPrefersTheLongestOperator(): void
    {
        $tokenizer = new MySqlTokenizer([], [], true);

        self::assertSame('<=>', $tokenizer->operatorAt('<=>', 0));
        self::assertSame('<=', $tokenizer->operatorAt('<=x', 0));
        self::assertSame('<', $tokenizer->operatorAt('<x', 0));
    }

    public function testOperatorAtReportsNothingForAWord(): void
    {
        self::assertNull((new MySqlTokenizer([], [], true))->operatorAt('users', 0));
    }

    public function testIsPunctuationAcceptsASingleGrammarCharacter(): void
    {
        $tokenizer = new MySqlTokenizer([], [], true);

        self::assertTrue($tokenizer->isPunctuation('('));
        self::assertFalse($tokenizer->isPunctuation('a'));
        self::assertFalse($tokenizer->isPunctuation('<='));
    }

    public function testMergedLeavesTokensThatDoNotPairUp(): void
    {
        self::assertSame(
            ['SELECT_SYM', 'IDENT'],
            (new MySqlTokenizer([], [], true))->merged(['SELECT_SYM', 'IDENT']),
        );
    }

    public function testMergedJoinsWithRollup(): void
    {
        self::assertSame(['WITH_ROLLUP_SYM'], (new MySqlTokenizer([], [], true))->merged(['WITH', 'ROLLUP_SYM']));
    }
}
