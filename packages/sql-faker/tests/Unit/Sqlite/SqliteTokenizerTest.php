<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalException;
use SqlFaker\Sqlite\SqliteTokenizer;

#[CoversClass(SqliteTokenizer::class)]
#[UsesClass(LexicalException::class)]
final class SqliteTokenizerTest extends TestCase
{
    /**
     * @param list<string> $expected
     */
    #[DataProvider('providerSql')]
    public function testTokenizeReadsTextAsTheServerWould(string $sql, array $expected): void
    {
        self::assertSame($expected, (new SqliteTokenizer(['SELECT' => 'SELECT']))->tokenize($sql));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function providerSql(): iterable
    {
        yield 'nothing' => ['', []];
        yield 'keyword' => ['SELECT', ['SELECT']];
        yield 'keyword regardless of case' => ['select', ['SELECT']];
        yield 'identifier' => ['users', ['ID']];
        yield 'double quoted identifier' => ['"users"', ['ID']];
        yield 'backtick quoted identifier' => ['`users`', ['ID']];
        yield 'bracket quoted identifier' => ['[users]', ['ID']];
        yield 'string literal' => ["'text'", ['STRING']];
        yield 'blob literal' => ["X'FF'", ['BLOB']];
        yield 'anonymous parameter' => ['?', ['VARIABLE']];
        yield 'numbered parameter' => ['?1', ['VARIABLE']];
        yield 'named parameter' => [':name', ['VARIABLE']];
        yield 'at parameter' => ['@name', ['VARIABLE']];
        yield 'dollar parameter' => ['$name', ['VARIABLE']];
        yield 'integer' => ['42', ['INTEGER']];
        yield 'hex integer' => ['0xFF', ['INTEGER']];
        yield 'separated integer' => ['1_0', ['QNUMBER']];
        yield 'float' => ['1.5', ['FLOAT']];
        yield 'exponent float' => ['1e3', ['FLOAT']];
        yield 'pointer' => ['->', ['PTR']];
        yield 'double pointer' => ['->>', ['PTR']];
        yield 'concatenation' => ['||', ['CONCAT']];
        yield 'double equals is equality' => ['==', ['EQ']];
        yield 'punctuation' => ['(', ['LP']];
        yield 'line comment is trivia' => ["SELECT -- note\nusers", ['SELECT', 'ID']];
        yield 'block comment is trivia' => ['SELECT /* note */ users', ['SELECT', 'ID']];
    }

    public function testTokenizeReportsTextItCannotRead(): void
    {
        $tokenizer = new SqliteTokenizer([]);

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported SQLite lexical input at offset 0:');

        $tokenizer->tokenize('\\');
    }

    public function testTokenizeReportsAnUnterminatedBracketIdentifier(): void
    {
        $tokenizer = new SqliteTokenizer([]);

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated SQLite bracket identifier.');

        $tokenizer->tokenize('[users');
    }

    public function testQuotedTokenAtLeavesAnythingThatOpensNoQuote(): void
    {
        $offset = 0;

        self::assertNull((new SqliteTokenizer([]))->quotedTokenAt('users', $offset));
        self::assertSame(0, $offset);
    }

    public function testLiteralTokenAtLeavesTextThatIsNoLiteral(): void
    {
        $offset = 0;

        self::assertNull((new SqliteTokenizer([]))->literalTokenAt('users', $offset));
    }

    public function testWordTokenAtLeavesTextThatIsNoWord(): void
    {
        $offset = 0;

        self::assertNull((new SqliteTokenizer([]))->wordTokenAt('42', $offset));
    }

    public function testOperatorTokenAtLeavesTextThatIsNoOperator(): void
    {
        $offset = 0;

        self::assertNull((new SqliteTokenizer([]))->operatorTokenAt('users', $offset));
    }

    public function testTokenAtReadsTheFirstTokenAndAdvancesPastIt(): void
    {
        $tokenizer = new SqliteTokenizer(['SELECT' => 'SELECT']);
        $offset = 0;

        self::assertSame('SELECT', $tokenizer->tokenAt('SELECT users', $offset));
        self::assertSame(6, $offset);
    }

    public function testSkipTriviaReportsWhenNothingWasSkipped(): void
    {
        $offset = 0;

        self::assertFalse((new SqliteTokenizer([]))->skipTrivia('SELECT', $offset));
    }

    public function testSkipTriviaReportsAnUnterminatedBlockComment(): void
    {
        $offset = 0;

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated SQLite block comment.');

        (new SqliteTokenizer([]))->skipTrivia('/* never closed', $offset);
    }

    public function testSkipQuotedTreatsADoubledQuoteAsAnEscape(): void
    {
        $offset = 0;

        (new SqliteTokenizer([]))->skipQuoted("'a''b' rest", $offset, "'");

        self::assertSame(6, $offset);
    }

    public function testSkipQuotedReportsARunThatNeverCloses(): void
    {
        $offset = 0;

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unterminated SQLite quoted token');

        (new SqliteTokenizer([]))->skipQuoted("'abc", $offset, "'");
    }

    public function testOperatorAtPrefersTheLongestOperator(): void
    {
        $tokenizer = new SqliteTokenizer([]);

        self::assertSame(['->>', 'PTR'], $tokenizer->operatorAt('->>', 0));
        self::assertSame(['->', 'PTR'], $tokenizer->operatorAt('->x', 0));
        self::assertSame(['-', 'MINUS'], $tokenizer->operatorAt('-x', 0));
    }

    public function testOperatorAtReportsNothingForAWord(): void
    {
        self::assertNull((new SqliteTokenizer([]))->operatorAt('users', 0));
    }

    public function testNormalizedSourceTokensStripsThePrefixAndTheSpacing(): void
    {
        self::assertSame(
            ['SELECT', 'ID'],
            (new SqliteTokenizer([]))->normalizedSourceTokens(['TK_SELECT', 'TK_SPACE', 'TK_ID']),
        );
    }

    public function testNormalizedSourceTokensLeavesUnprefixedNamesAlone(): void
    {
        self::assertSame(['SELECT'], (new SqliteTokenizer([]))->normalizedSourceTokens(['SELECT']));
    }
}
