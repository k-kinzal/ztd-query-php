<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

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
use SqlFaker\MySql\LexicalGrammar;
use SqlFaker\MySql\MySqlTerminalRealizer;
use SqlFaker\MySql\MySqlTokenizer;
use UnexpectedValueException;

#[CoversClass(LexicalGrammar::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(TokenJoiner::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(SqlVersion::class)]
#[UsesClass(LexicalCatalogException::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(LexicalKeywordIndex::class)]
#[UsesClass(LexicalProfileSource::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(SqlVersionRegistry::class)]
#[UsesClass(MySqlTerminalRealizer::class)]
#[UsesClass(MySqlTokenizer::class)]
final class LexicalGrammarTest extends TestCase
{
    public function testGeneratesPublicProviderLexemesThroughDialectGrammar(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'mysql-8.4.7');
        $sql = implode(' ', [
            $lexical->generateQuotedIdentifier(3, 3),
            $lexical->generateStringLiteral(3, 3),
            $lexical->generateNationalStringLiteral(3, 3),
            $lexical->generateDollarQuotedString(3, 3),
            $lexical->generateIntegerLiteral(10, 10),
            $lexical->generateDecimalLiteral(4, 2),
            $lexical->generateFloatLiteral(4, 2, 2, 2),
            $lexical->generateHexLiteral(4, 4),
            $lexical->generateQuotedHexLiteral(2, 2),
            $lexical->generateBinaryLiteral(4, 4),
        ]);

        self::assertSame([
            'IDENT_QUOTED', 'TEXT_STRING', 'NCHAR_STRING', 'DOLLAR_QUOTED_STRING_SYM', 'NUM',
            'DECIMAL_NUM', 'FLOAT_NUM', 'HEX_NUM', 'HEX_NUM', 'BIN_NUM',
        ], $lexical->tokenize($sql));
        self::assertSame('10', $lexical->generateLongIntegerLiteral(10, 10));
        self::assertMatchesRegularExpression('/^[0-9]{1,20}$/', $lexical->generateUnsignedBigIntLiteral());
        self::assertSame(
            ['@', 'LEX_HOSTNAME'],
            $lexical->tokenize('@' . $lexical->generateHostname(2, 2, 3)),
        );
    }

    /**
     * @param Closure(LexicalGrammar): string $withDefaults
     * @param Closure(LexicalGrammar): string $withExplicitBounds
     */
    #[DataProvider('providerPublicLexemeDefaults')]
    public function testPublicLexemeDefaultBounds(Closure $withDefaults, Closure $withExplicitBounds): void
    {
        $faker = Factory::create();
        $grammar = new LexicalGrammar($faker, 'mysql-8.4.7');

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
            static fn (LexicalGrammar $grammar): string => $grammar->generateQuotedIdentifier(1, 64),
        ];
        yield 'string' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(1, 255),
        ];
        yield 'national string' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateNationalStringLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateNationalStringLiteral(1, 255),
        ];
        yield 'dollar quoted string' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateDollarQuotedString(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateDollarQuotedString(1, 255),
        ];
        yield 'integer' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(1, 2147483647),
        ];
        yield 'long integer' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateLongIntegerLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateLongIntegerLiteral(0, 2147483647),
        ];
        yield 'unsigned big integer' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateUnsignedBigIntLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateUnsignedBigIntLiteral(1, 20),
        ];
        yield 'decimal' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(10, 2),
        ];
        yield 'float' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateFloatLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateFloatLiteral(10, 2, -38, 38),
        ];
        yield 'hex' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateHexLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateHexLiteral(1, 16),
        ];
        yield 'quoted hex' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateQuotedHexLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateQuotedHexLiteral(1, 8),
        ];
        yield 'binary' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateBinaryLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateBinaryLiteral(1, 64),
        ];
        yield 'hostname' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateHostname(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateHostname(1, 4, 63),
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
            (new LexicalGrammar($faker, 'mysql-8.4.7', true))->realize(['TEXT_STRING']),
        );
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function providerGeneratedStringLiteral(): iterable
    {
        yield 'combined arm value zero' => [0, "'ACCESSIBLE ACCESSIBLE'"];
        yield 'combined arm value one' => [1, "'ACCESSIBLE ACCESSIBLE'"];
        yield 'quote escaping' => [2, "'a''b'"];
        yield 'backslash' => [3, "'a\\b'"];
        yield 'random body' => [4, "''"];
    }

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

        $this->expectException(RuntimeException::class);
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

    public function testGenerateQuotedIdentifierWrapsTheNameInBackticks(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^`.+`$/',
            $lexical->generateQuotedIdentifier(),
        );
    }

    public function testGenerateStringLiteralWrapsTheBodyInSingleQuotes(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^\'.*\'$/s',
            $lexical->generateStringLiteral(),
        );
    }

    public function testGenerateNationalStringLiteralPrefixesTheStringWithN(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^N\'.*\'$/s',
            $lexical->generateNationalStringLiteral(),
        );
    }

    public function testGenerateDollarQuotedStringWrapsTheBodyInDoubleDollars(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^\$\$.*\$\$$/s',
            $lexical->generateDollarQuotedString(),
        );
    }

    public function testGenerateIntegerLiteralWritesOnlyDigits(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^\d+$/',
            $lexical->generateIntegerLiteral(),
        );
    }

    public function testGenerateLongIntegerLiteralWritesOnlyDigits(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^\d+$/',
            $lexical->generateLongIntegerLiteral(),
        );
    }

    public function testGenerateUnsignedBigIntLiteralWritesOnlyDigits(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^\d+$/',
            $lexical->generateUnsignedBigIntLiteral(),
        );
    }

    public function testGenerateDecimalLiteralWritesDigitsAroundAPoint(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^-?\d+\.\d+$/',
            $lexical->generateDecimalLiteral(),
        );
    }

    public function testGenerateFloatLiteralWritesAnExponent(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/[eE][+-]?\d+$/',
            $lexical->generateFloatLiteral(),
        );
    }

    public function testGenerateHexLiteralWritesHexDigitsAfterAPrefix(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^0x[0-9a-fA-F]+$/',
            $lexical->generateHexLiteral(),
        );
    }

    public function testGenerateQuotedHexLiteralWritesWholeBytesInQuotes(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^X\'(?:[0-9a-fA-F]{2})+\'$/',
            $lexical->generateQuotedHexLiteral(),
        );
    }

    public function testGenerateBinaryLiteralWritesBitsAfterAPrefix(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^0b[01]+$/',
            $lexical->generateBinaryLiteral(),
        );
    }

    public function testGenerateHostnameWritesDotSeparatedParts(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        self::assertMatchesRegularExpression(
            '/^[A-Za-z0-9.-]+$/',
            $lexical->generateHostname(),
        );
    }

    public function testSupportsAcceptsATerminalTheCatalogWitnesses(): void
    {
        self::assertTrue((new LexicalGrammar(Factory::create(), 'mysql-8.4.7'))->supports('SELECT_SYM'));
    }

    public function testSupportsRejectsATerminalNoCatalogWitnesses(): void
    {
        self::assertFalse((new LexicalGrammar(Factory::create(), 'mysql-8.4.7'))->supports('NOT_A_TERMINAL'));
    }

    public function testAssertTerminalsCoveredAcceptsTerminalsTheProfileClassifies(): void
    {
        (new LexicalGrammar(Factory::create(), 'mysql-8.4.7'))->assertTerminalsCovered(['SELECT_SYM', 'IDENT']);

        $this->expectNotToPerformAssertions();
    }

    public function testAssertTerminalsCoveredReportsATerminalTheProfileDoesNotClassify(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'mysql-8.4.7');

        $this->expectException(LexicalCatalogException::class);
        $this->expectExceptionMessage('missing grammar terminals: NOT_A_TERMINAL');

        $lexical->assertTerminalsCovered(['NOT_A_TERMINAL']);
    }
}
