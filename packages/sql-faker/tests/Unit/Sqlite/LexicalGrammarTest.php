<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\Sqlite\LexicalGrammar;

#[CoversClass(LexicalGrammar::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(TokenJoiner::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(SqlVersion::class)]
final class LexicalGrammarTest extends TestCase
{
    public function testTokenizesQuotedIdentifiersStringsVariablesAndComments(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');
        $sql = <<<'SQL'
SELECT "values", [select], `from`, 'FROM ''items''', X'af', ?, ?12, :name, @name, $name
/* UPDATE */ -- DELETE
FROM items
SQL;

        self::assertSame([
            'SELECT', 'ID', 'COMMA', 'ID', 'COMMA', 'ID', 'COMMA', 'STRING', 'COMMA', 'BLOB', 'COMMA',
            'VARIABLE', 'COMMA', 'VARIABLE', 'COMMA', 'VARIABLE', 'COMMA', 'VARIABLE', 'COMMA', 'VARIABLE',
            'FROM', 'ID',
        ], $lexical->tokenize($sql));
    }

    public function testUsesVersionedKeywordFamilies(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        self::assertSame(['JOIN_KW', 'JOIN_KW', 'CTIME_KW'], $lexical->tokenize('LEFT CROSS CURRENT_TIMESTAMP'));
    }

    public function testTokenizesEveryNumberAndOperatorClass(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        self::assertSame([
            'INTEGER', 'QNUMBER', 'INTEGER', 'FLOAT', 'FLOAT', 'FLOAT', 'FLOAT',
            'PTR', 'PTR', 'CONCAT', 'EQ', 'LE', 'NE', 'NE', 'GE', 'LSHIFT', 'RSHIFT',
            'LP', 'RP', 'SEMI', 'COMMA', 'DOT', 'EQ', 'LT', 'GT', 'PLUS', 'MINUS', 'STAR',
            'SLASH', 'REM', 'BITAND', 'BITOR', 'BITNOT',
        ], $lexical->tokenize(
            '0 1_0 0xAf 1.5 .5 1e2 1.e-2 '
            . '->> -> || == <= <> != >= << >> ( ) ; , . = < > + - * / % & | ~',
        ));
    }

    public function testTokenizesKeywordsCaseInsensitivelyAndSkipsEmbeddedComments(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        self::assertSame(
            ['SELECT', 'ID', 'FROM', 'ID'],
            $lexical->tokenize("select-- comment\nname/* comment */from items"),
        );
    }

    public function testReportsVersionSupportAndMissingTerminal(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        self::assertSame('sqlite-3.47.2', $lexical->version());
        self::assertTrue($lexical->supports('ID'));
        self::assertFalse($lexical->supports('NOT_A_TERMINAL'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('NOT_A_TERMINAL');

        $lexical->assertTerminalsCovered(['NOT_A_TERMINAL']);
    }

    #[DataProvider('providerInvalidSql')]
    public function testRejectsInvalidSql(string $sql, string $message): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage($message);

        $lexical->tokenize($sql);
    }

    public function testRealizesTokenClassesAndWildcardWithoutInventingLexerTokens(): void
    {
        $faker = Factory::create();
        $faker->seed(22);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');
        $sql = $lexical->realize(['ID', 'COMMA', 'STRING', 'COMMA', 'QNUMBER', 'COMMA', 'ANY']);

        self::assertSame(['ID', 'COMMA', 'STRING', 'COMMA', 'QNUMBER', 'COMMA', 'ID'], $lexical->tokenize($sql));
    }

    public function testRealizationCanPlaceACommentBeforeTheFirstToken(): void
    {
        $faker = Factory::create();
        $faker->seed(20260815);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');
        $statements = array_map(
            static fn (int $iteration): string => $lexical->realize(['SELECT', 'ID']),
            range(1, 32),
        );

        self::assertNotEmpty(array_filter(
            $statements,
            static fn (string $sql): bool => preg_match('/^\s*(?:--|\/\*)/', $sql) === 1,
        ));
    }

    public function testSyntheticRealizationDisablesTriviaAndAcceptsUnknownTerminals(): void
    {
        $faker = Factory::create();
        $faker->seed(1);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2', true);

        self::assertTrue($lexical->supports('UNKNOWN'));
        self::assertSame('SELECT', $lexical->realize(['SELECT']));
        self::assertSame('->*', $lexical->realize(['PTR', 'STAR']));
        self::assertSame('*->', $lexical->realize(['STAR', 'PTR']));
        self::assertSame([], $lexical->tokenize(''));
    }

    #[DataProvider('providerFixedSyntheticTerminal')]
    public function testSyntheticRealizationOfFixedTerminal(string $terminal, string $expected): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2', true);

        self::assertSame($expected, $lexical->realize([$terminal]));
    }

    #[DataProvider('providerIdentifierSyntheticTerminal')]
    public function testSyntheticRealizationOfIdentifier(string $terminal): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2', true);

        self::assertNotSame($terminal, $lexical->realize([$terminal]));
    }

    #[DataProvider('providerStringSyntheticTerminal')]
    public function testSyntheticRealizationOfString(string $terminal): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2', true);

        self::assertStringStartsWith("'", $lexical->realize([$terminal]));
    }

    public function testSyntheticRealizationOfGeneratedTerminals(): void
    {
        $faker = Factory::create();
        $faker->seed(17);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2', true);

        self::assertMatchesRegularExpression("/^X'[0-9a-f]*'$/", $lexical->realize(['BLOB']));
        self::assertMatchesRegularExpression('/^\d+$/', $lexical->realize(['number']));
        self::assertMatchesRegularExpression('/^\d+$/', $lexical->realize(['INTEGER']));
        self::assertMatchesRegularExpression('/^(?:\?\d*|[:@$][A-Za-z_][A-Za-z0-9_]*)$/', $lexical->realize(['VARIABLE']));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerInvalidSql(): iterable
    {
        yield 'bracket identifier' => ['[name', 'Unterminated SQLite bracket identifier.'];
        yield 'single quoted string' => ["'value", 'Unterminated SQLite quoted token'];
        yield 'double quoted identifier' => ['"name', 'Unterminated SQLite quoted token'];
        yield 'backtick quoted identifier' => ['`name', 'Unterminated SQLite quoted token'];
        yield 'block comment' => ['/* comment', 'Unterminated SQLite block comment.'];
        yield 'unsupported character' => ['SELECT \\', 'offset 7: SELECT \\'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerFixedSyntheticTerminal(): iterable
    {
        yield 'QNUMBER' => ['QNUMBER', '1_0'];
        yield 'ANY' => ['ANY', '_any'];
        yield 'LP' => ['LP', '('];
        yield 'RP' => ['RP', ')'];
        yield 'SEMI' => ['SEMI', ';'];
        yield 'COMMA' => ['COMMA', ','];
        yield 'DOT' => ['DOT', '.'];
        yield 'EQ' => ['EQ', '='];
        yield 'LT' => ['LT', '<'];
        yield 'PLUS' => ['PLUS', '+'];
        yield 'MINUS' => ['MINUS', '-'];
        yield 'STAR' => ['STAR', '*'];
        yield 'BITAND' => ['BITAND', '&'];
        yield 'BITNOT' => ['BITNOT', '~'];
        yield 'CONCAT' => ['CONCAT', '||'];
        yield 'PTR' => ['PTR', '->'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerIdentifierSyntheticTerminal(): iterable
    {
        yield 'ID' => ['ID'];
        yield 'id' => ['id'];
        yield 'idj' => ['idj'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerStringSyntheticTerminal(): iterable
    {
        yield 'ids' => ['ids'];
        yield 'STRING' => ['STRING'];
    }
}
