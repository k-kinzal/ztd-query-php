<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite\Lexical;

use Closure;
use Faker\Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SqlFaker\Grammar\Lexical\LexicalCatalog;
use SqlFaker\Grammar\Lexical\LexicalCatalogException;
use SqlFaker\Grammar\Lexical\LexicalCatalogShape;
use SqlFaker\Grammar\Lexical\LexicalCoverageCheck;
use SqlFaker\Grammar\Lexical\LexicalException;
use SqlFaker\Grammar\Lexical\LexicalKeywordIndex;
use SqlFaker\Grammar\Lexical\LexicalProfileSource;
use SqlFaker\Grammar\Lexical\LexicalWitnessCheck;
use SqlFaker\Grammar\Lexical\LexicalWitnessShape;
use SqlFaker\Grammar\Lexical\RandomCharacters;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\Source\SqlVersion;
use SqlFaker\Grammar\Source\SqlVersionRegistry;
use SqlFaker\Grammar\Source\TokenJoiner;
use SqlFaker\Sqlite\Lexical\LexicalGrammar;
use SqlFaker\Sqlite\Lexical\SqliteTerminalRealizer;
use SqlFaker\Sqlite\Lexical\SqliteTokenizer;
use UnexpectedValueException;

#[CoversClass(LexicalGrammar::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(TokenJoiner::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalCatalogException::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(LexicalKeywordIndex::class)]
#[UsesClass(LexicalProfileSource::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(SqliteTerminalRealizer::class)]
#[UsesClass(SqliteTokenizer::class)]
final class LexicalGrammarTest extends TestCase
{
    public function testGenerateQuotedIdentifierWritesWhatTheLexerReadsBackAsAnIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertSame(['ID'], $lexical->tokenize($lexical->generateQuotedIdentifier(3, 3)));
    }

    public function testGenerateStringLiteralWritesWhatTheLexerReadsBackAsAString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertSame(['STRING'], $lexical->tokenize($lexical->generateStringLiteral(3, 3)));
    }

    public function testGenerateIntegerLiteralWritesWhatTheLexerReadsBackAsAnInteger(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertSame(['INTEGER'], $lexical->tokenize($lexical->generateIntegerLiteral(10, 10)));
    }

    public function testGenerateDecimalLiteralWritesWhatTheLexerReadsBackAsAFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'sqlite-3.47.2');

        self::assertSame(['FLOAT'], $lexical->tokenize($lexical->generateDecimalLiteral(4, 2)));
    }

    public function testVersionReportsTheReleaseTheProfileWasBuiltFor(): void
    {
        self::assertSame('sqlite-3.47.2', (new LexicalGrammar(Factory::create(), 'sqlite-3.47.2'))->version());
    }

    public function testSupportsAcceptsATerminalTheSqliteTokenizerCanWrite(): void
    {
        self::assertTrue((new LexicalGrammar(Factory::create(), 'sqlite-3.47.2'))->supports('ID'));
    }

    public function testSupportsRejectsATerminalNoSqliteTokenizerDeclares(): void
    {
        self::assertFalse((new LexicalGrammar(Factory::create(), 'sqlite-3.47.2'))->supports('NO_SUCH_TERMINAL'));
    }

    public function testAssertTerminalsCoveredAcceptsATerminalTheCatalogWitnesses(): void
    {
        (new LexicalGrammar(Factory::create(), 'sqlite-3.47.2'))->assertTerminalsCovered(['ID']);

        $this->expectNotToPerformAssertions();
    }

    public function testAssertTerminalsCoveredReportsATerminalTheCatalogNeitherWitnessesNorExcludes(): void
    {
        $this->expectException(LexicalCatalogException::class);

        (new LexicalGrammar(Factory::create(), 'sqlite-3.47.2'))->assertTerminalsCovered(['NO_SUCH_TERMINAL']);
    }

    /**
     * @param Closure(LexicalGrammar): string $withDefaults
     * @param Closure(LexicalGrammar): string $withExplicitBounds
     */
    #[DataProvider('providerPublicLexemeDefaults')]
    public function testPublicLexemeDefaultBounds(Closure $withDefaults, Closure $withExplicitBounds): void
    {
        $faker = Factory::create();
        $grammar = new LexicalGrammar($faker, 'sqlite-3.47.2');

        $faker->seed(20_260_824);
        $generated = $withDefaults($grammar);

        $faker->seed(20_260_824);
        $explicit = $withExplicitBounds($grammar);

        self::assertNotSame('', $generated);
        self::assertSame($generated, $explicit);
    }

    /**
     * @return iterable<string, array{Closure(LexicalGrammar): string, Closure(LexicalGrammar): string}>
     */
    public static function providerPublicLexemeDefaults(): iterable
    {
        yield 'quoted identifier' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateQuotedIdentifier(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateQuotedIdentifier(1, 128),
        ];
        yield 'string' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(1, 255),
        ];
        yield 'integer' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(1, PHP_INT_MAX),
        ];
        yield 'decimal' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(15, 2),
        ];
    }

    #[DataProvider('providerGeneratedStringLiteral')]
    public function testGeneratesEveryStringLiteralStrategy(int $choice, string $expected): void
    {
        $faker = new class ($choice) extends \Faker\Generator {
            private bool $first = true;

            public function __construct(private readonly int $choice)
            {
                parent::__construct();
            }

            /**
             * @param mixed $min
             * @param mixed $max
             *
             * @throws UnexpectedValueException When the bound is not an integer
             */
            #[Override]
            public function numberBetween($min = 0, $max = 2147483647): int
            {
                if ($this->first) {
                    $this->first = false;

                    return $this->choice;
                }
                if (!is_int($min)) {
                    throw new UnexpectedValueException();
                }

                return $min;
            }
        };

        self::assertSame(
            $expected,
            (new LexicalGrammar($faker, 'sqlite-3.47.2', true))->realize(['STRING']),
        );
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function providerGeneratedStringLiteral(): iterable
    {
        yield 'combined arm value zero' => [0, "'ABORT ABORT'"];
        yield 'combined arm value one' => [1, "'ABORT ABORT'"];
        yield 'quote escaping' => [2, "'a''b'"];
        yield 'backslash' => [3, "'a\\b'"];
        yield 'random body' => [4, "''"];
    }

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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('NOT_A_TERMINAL');

        $lexical->assertTerminalsCovered(['NOT_A_TERMINAL']);
    }

    public function testRealizesStrictTableOptionAsIdentifierToken(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'sqlite-3.47.2');

        self::assertTrue($lexical->supports(LexicalGrammar::STRICT_TABLE_OPTION));
        $lexical->assertTerminalsCovered(['ID', LexicalGrammar::STRICT_TABLE_OPTION]);
        $sql = $lexical->realize([LexicalGrammar::STRICT_TABLE_OPTION]);
        self::assertStringContainsString('STRICT', $sql);
        self::assertSame(['ID'], $lexical->tokenize($sql));
        self::assertSame(['ID'], $lexical->tokenize('STRICT'));
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

    #[DataProvider('providerIdentifierQuoteStrategy')]
    public function testIdentifierQuoteStrategy(int $choice, string $expected): void
    {
        $faker = new class ([0, $choice]) extends \Faker\Generator {
            private int $call = 0;

            /**
             * @param list<int> $choices
             */
            public function __construct(private readonly array $choices)
            {
                parent::__construct();
            }

            /**
             * @param mixed $min
             * @param mixed $max
             *
             * @throws UnexpectedValueException When the bound is not an integer
             */
            #[Override]
            public function numberBetween($min = 0, $max = 2147483647): int
            {
                if (isset($this->choices[$this->call])) {
                    return $this->choices[$this->call++];
                }
                if (!is_int($min)) {
                    throw new UnexpectedValueException();
                }

                return $min;
            }
        };

        self::assertSame(
            $expected,
            (new LexicalGrammar($faker, 'sqlite-3.47.2', true))->realize(['ID']),
        );
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function providerIdentifierQuoteStrategy(): iterable
    {
        yield 'double quote escaping' => [0, '"select""quoted"'];
        yield 'backtick escaping' => [1, '`select``quoted`'];
        yield 'closing bracket removal' => [2, '[selectquoted]'];
        yield 'unquoted identifier' => [3, 'select'];
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
