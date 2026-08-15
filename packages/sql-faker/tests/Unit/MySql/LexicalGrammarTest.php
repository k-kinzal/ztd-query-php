<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\MySql\LexicalGrammar;

#[CoversClass(LexicalGrammar::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(TokenJoiner::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(SqlVersion::class)]
final class LexicalGrammarTest extends TestCase
{
    public function testTokenizesQuotedValuesHexValuesAndCommentsAsSingleTokens(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');
        $sql = <<<'SQL'
SELECT `values`, 'FROM ''items''', X'af', B'101' /* UPDATE */ # DELETE
FROM items
SQL;

        self::assertSame([
            'SELECT_SYM', 'IDENT_QUOTED', ',', 'TEXT_STRING', ',', 'HEX_NUM', ',', 'BIN_NUM', 'FROM', 'IDENT',
        ], $lexical->tokenize($sql));
    }

    public function testTokenizesEveryLiteralNumberAndOperatorClass(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');
        $sql = <<<'SQL'
0 2147483647 2147483648 9223372036854775807 9223372036854775808 000 000000000002147483648
0XAF 0b10 x'af' b'01' n'x' 1.2 .5 1E2 _UTF8MB4 _name ?
WITH ROLLUP || <=> ->> && <= <> != >= << >> := ->
! % & ( ) * + , - . / : ; @ ^ { } | ~ = < >
SQL;

        self::assertSame([
            'NUM', 'NUM', 'LONG_NUM', 'LONG_NUM', 'ULONGLONG_NUM', 'NUM', 'LONG_NUM',
            'HEX_NUM', 'BIN_NUM', 'HEX_NUM', 'BIN_NUM', 'NCHAR_STRING', 'DECIMAL_NUM', 'DECIMAL_NUM',
            'FLOAT_NUM', 'UNDERSCORE_CHARSET', 'IDENT', 'PARAM_MARKER',
            'WITH_ROLLUP_SYM', 'OR2_SYM', 'EQUAL_SYM', '->>', 'AND_AND_SYM', 'LE', 'NE', 'NE', 'GE',
            'SHIFT_LEFT', 'SHIFT_RIGHT', ':=', '->',
            '!', '%', '&', '(', ')', '*', '+', ',', '-', '.', '/', ':', ';', '@', '^', '{', '}', '|', '~',
            'EQ', 'LT', 'GT_SYM',
        ], $lexical->tokenize($sql));
    }

    public function testTokenizesDollarQuotedStringAndContinuesWithTheRemainingInput(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertSame(
            ['DOLLAR_QUOTED_STRING_SYM', 'PARAM_MARKER'],
            $lexical->tokenize('$$value$$ ?'),
        );
    }

    public function testCombinesWithRollupAtTheEndOfInput(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertSame(['WITH_ROLLUP_SYM'], $lexical->tokenize('WITH ROLLUP'));
    }

    public function testUsesFunctionTokensOnlyWhenFollowedByAnOpeningParenthesis(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertSame(
            ['SELECT_SYM', 'IDENT', 'COUNT_SYM', '(', '*', ')'],
            $lexical->tokenize('select count COUNT(*)'),
        );
    }

    public function testVersionProfileControlsDollarQuotedStringSupport(): void
    {
        $faker = Factory::create();

        $beforeSupport = new LexicalGrammar($faker, 'mysql-8.0.44');
        $afterSupport = new LexicalGrammar($faker, 'mysql-8.1.0');

        self::assertSame('mysql-8.0.44', $beforeSupport->version());
        self::assertFalse($beforeSupport->supports('DOLLAR_QUOTED_STRING_SYM'));
        self::assertTrue($afterSupport->supports('DOLLAR_QUOTED_STRING_SYM'));
        self::assertFalse($afterSupport->supports('NOT_A_TERMINAL'));
    }

    public function testRejectsAMissingGrammarTerminal(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NOT_A_TERMINAL');

        $lexical->assertTerminalsCovered(['NOT_A_TERMINAL']);
    }

    #[DataProvider('providerInvalidSql')]
    public function testRejectsInvalidSql(string $version, string $sql, string $message): void
    {
        $lexical = new LexicalGrammar(Factory::create(), $version);

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage($message);

        $lexical->tokenize($sql);
    }

    public function testTokenizesHostnameWithoutStoppingTheRemainingInput(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertSame(
            ['SELECT_SYM', 'IDENT', '@', 'LEX_HOSTNAME', 'FROM', 'IDENT'],
            $lexical->tokenize('SELECT sqlfakeruser@host.example FROM items'),
        );
    }

    public function testRealizesAndChecksAParserTokenSequence(): void
    {
        $faker = Factory::create();
        $faker->seed(12);
        $lexical = new LexicalGrammar($faker, 'mysql-8.4.7');

        $sql = $lexical->realize(['SELECT_SYM', 'IDENT_QUOTED', 'FROM', 'IDENT', 'WHERE', 'IDENT', 'EQ', 'HEX_NUM']);

        self::assertSame([
            'SELECT_SYM', 'IDENT_QUOTED', 'FROM', 'IDENT', 'WHERE', 'IDENT', 'EQ', 'HEX_NUM',
        ], $lexical->tokenize($sql));
    }

    public function testRealizationCanPlaceACommentBeforeTheFirstToken(): void
    {
        $faker = Factory::create();
        $faker->seed(20260815);
        $lexical = new LexicalGrammar($faker, 'mysql-8.4.7');
        $statements = array_map(
            static fn (int $iteration): string => $lexical->realize(['SELECT_SYM', 'IDENT']),
            range(1, 32),
        );

        self::assertNotEmpty(array_filter(
            $statements,
            static fn (string $sql): bool => preg_match('/^\s*(?:--|#|\/\*)/', $sql) === 1,
        ));
    }

    public function testRejectsAContextThatChangesTheDerivedToken(): void
    {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Expected: ["@","IDENT"]');

        (new LexicalGrammar(Factory::create(), 'mysql-8.4.7'))->realize(['@', 'IDENT']);
    }

    public function testSyntheticRealizationDisablesTriviaAndAcceptsUnknownTerminals(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7', true);

        self::assertTrue($lexical->supports('NOT_A_TERMINAL'));
        self::assertSame('', $lexical->realize(['GRAMMAR_SELECTOR_TEST', 'END_OF_INPUT']));
        self::assertSame('SELECT', $lexical->realize(['SELECT_SYM']));
        self::assertSame([], $lexical->tokenize(''));
    }

    #[DataProvider('providerFixedSyntheticTerminal')]
    public function testSyntheticRealizationOfFixedTerminal(string $terminal, string $expected): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7', true);

        self::assertSame($expected, $lexical->realize([$terminal]));
    }

    public function testSyntheticRealizationOfGeneratedTerminals(): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $lexical = new LexicalGrammar($faker, 'mysql-8.4.7', true);

        self::assertMatchesRegularExpression('/^_[A-Za-z0-9_]+$/', $lexical->realize(['IDENT']));
        self::assertStringStartsWith('`', $lexical->realize(['IDENT_QUOTED']));
        self::assertStringStartsWith("'", $lexical->realize(['TEXT_STRING']));
        self::assertStringStartsWith("N'", $lexical->realize(['NCHAR_STRING']));
        self::assertStringStartsWith('$$', $lexical->realize(['DOLLAR_QUOTED_STRING_SYM']));
        self::assertMatchesRegularExpression('/^\d+$/', $lexical->realize(['NUM']));
        self::assertMatchesRegularExpression('/^\d+$/', $lexical->realize(['LONG_NUM']));
        self::assertSame('18446744073709551615', $lexical->realize(['ULONGLONG_NUM']));
        self::assertMatchesRegularExpression('/^\d+\.\d+$/', $lexical->realize(['DECIMAL_NUM']));
        self::assertMatchesRegularExpression('/^[+-]?\d+(?:\.\d+)?[eE][+-]?\d+$/', $lexical->realize(['FLOAT_NUM']));
        self::assertSame(['HEX_NUM'], $lexical->tokenize($lexical->realize(['HEX_NUM'])));
        self::assertSame(['BIN_NUM'], $lexical->tokenize($lexical->realize(['BIN_NUM'])));
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function providerInvalidSql(): iterable
    {
        yield 'backtick quoted identifier' => ['mysql-8.4.7', '`name', 'Unterminated MySQL quoted token'];
        yield 'single quoted string' => ['mysql-8.4.7', "'value", 'Unterminated MySQL quoted token'];
        yield 'dollar quoted string' => ['mysql-8.4.7', '$$value', 'Unterminated MySQL dollar-quoted string.'];
        yield 'block comment' => ['mysql-8.4.7', '/* comment', 'Unterminated MySQL block comment.'];
        yield 'unsupported character' => ['mysql-8.4.7', 'SELECT \\', 'offset 7: SELECT \\'];
        yield 'dollar quote before support' => ['mysql-8.0.44', '$$value$$', 'offset 0: $$value$$'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerFixedSyntheticTerminal(): iterable
    {
        yield 'EQ' => ['EQ', '='];
        yield 'EQUAL_SYM' => ['EQUAL_SYM', '<=>'];
        yield 'LT' => ['LT', '<'];
        yield 'GT_SYM' => ['GT_SYM', '>'];
        yield 'LE' => ['LE', '<='];
        yield 'GE' => ['GE', '>='];
        yield 'NE' => ['NE', '<>'];
        yield 'SHIFT_LEFT' => ['SHIFT_LEFT', '<<'];
        yield 'SHIFT_RIGHT' => ['SHIFT_RIGHT', '>>'];
        yield 'AND_AND_SYM' => ['AND_AND_SYM', '&&'];
        yield 'OR_OR_SYM' => ['OR_OR_SYM', '||'];
        yield 'NOT2_SYM' => ['NOT2_SYM', 'NOT'];
        yield 'SET_VAR' => ['SET_VAR', ':='];
        yield 'JSON_SEPARATOR_SYM' => ['JSON_SEPARATOR_SYM', '->'];
        yield 'JSON_UNQUOTED_SEPARATOR_SYM' => ['JSON_UNQUOTED_SEPARATOR_SYM', '->>'];
        yield 'NEG' => ['NEG', '-'];
        yield 'WITH_ROLLUP_SYM' => ['WITH_ROLLUP_SYM', 'WITH ROLLUP'];
        yield 'UNDERSCORE_CHARSET' => ['UNDERSCORE_CHARSET', '_utf8mb4'];
        yield 'PARAM_MARKER' => ['PARAM_MARKER', '?'];
    }
}
