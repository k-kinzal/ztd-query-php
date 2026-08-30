<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql\Lexical;

use Closure;
use Faker\Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
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
use SqlFaker\PostgreSql\Lexical\LexicalGrammar;
use SqlFaker\PostgreSql\Lexical\PgLookahead;
use SqlFaker\PostgreSql\Lexical\PgTerminalRealizer;
use SqlFaker\PostgreSql\Lexical\PgTokenizer;
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
#[UsesClass(PgLookahead::class)]
#[UsesClass(PgTerminalRealizer::class)]
#[UsesClass(PgTokenizer::class)]
final class LexicalGrammarTest extends TestCase
{
    public function testGenerateQuotedIdentifierWritesWhatTheLexerReadsBackAsAnIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['IDENT'], $lexical->tokenize($lexical->generateQuotedIdentifier(3, 3)));
    }

    public function testGenerateStringLiteralWritesWhatTheLexerReadsBackAsAString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['SCONST'], $lexical->tokenize($lexical->generateStringLiteral(3, 3)));
    }

    public function testGenerateIntegerLiteralWritesWhatTheLexerReadsBackAsAnInteger(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['ICONST'], $lexical->tokenize($lexical->generateIntegerLiteral(10, 10)));
    }

    public function testGenerateDecimalLiteralWritesWhatTheLexerReadsBackAsAFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['FCONST'], $lexical->tokenize($lexical->generateDecimalLiteral(4, 2)));
    }

    public function testGenerateFloatLiteralWritesWhatTheLexerReadsBackAsAFloat(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['FCONST'], $lexical->tokenize($lexical->generateFloatLiteral(4, 2, 2, 2)));
    }

    public function testGenerateHexLiteralWritesWhatTheLexerReadsBackAsAHexString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['XCONST'], $lexical->tokenize($lexical->generateHexLiteral(4, 4)));
    }

    public function testGenerateBinaryLiteralWritesWhatTheLexerReadsBackAsABitString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['BCONST'], $lexical->tokenize($lexical->generateBinaryLiteral(4, 4)));
    }

    public function testGenerateDollarQuotedStringWritesWhatTheLexerReadsBackAsAString(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['SCONST'], $lexical->tokenize($lexical->generateDollarQuotedString(3, 3)));
    }

    public function testGenerateParameterMarkerWritesWhatTheLexerReadsBackAsAParameter(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertSame(['PARAM'], $lexical->tokenize($lexical->generateParameterMarker(2, 2)));
    }

    public function testVersionReportsTheReleaseTheProfileWasBuiltFor(): void
    {
        self::assertSame('pg-17.2', (new LexicalGrammar(Factory::create(), 'pg-17.2'))->version());
    }

    public function testSupportsAcceptsATerminalThePostgreSqlLexerCanWrite(): void
    {
        self::assertTrue((new LexicalGrammar(Factory::create(), 'pg-17.2'))->supports('IDENT'));
    }

    public function testSupportsRejectsATerminalNoPostgreSqlLexerDeclares(): void
    {
        self::assertFalse((new LexicalGrammar(Factory::create(), 'pg-17.2'))->supports('NO_SUCH_TERMINAL'));
    }

    public function testAssertTerminalsCoveredAcceptsATerminalTheCatalogWitnesses(): void
    {
        (new LexicalGrammar(Factory::create(), 'pg-17.2'))->assertTerminalsCovered(['IDENT']);

        $this->expectNotToPerformAssertions();
    }

    public function testAssertTerminalsCoveredReportsATerminalTheCatalogNeitherWitnessesNorExcludes(): void
    {
        $this->expectException(LexicalCatalogException::class);

        (new LexicalGrammar(Factory::create(), 'pg-17.2'))->assertTerminalsCovered(['NO_SUCH_TERMINAL']);
    }

    /**
     * @param Closure(LexicalGrammar): string $withDefaults
     * @param Closure(LexicalGrammar): string $withExplicitBounds
     */
    #[DataProvider('providerPublicLexemeDefaults')]
    public function testPublicLexemeDefaultBounds(Closure $withDefaults, Closure $withExplicitBounds): void
    {
        $faker = Factory::create();
        $grammar = new LexicalGrammar($faker, 'pg-17.2');

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
            static fn (LexicalGrammar $grammar): string => $grammar->generateQuotedIdentifier(1, 63),
        ];
        yield 'string' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(1, 255),
        ];
        yield 'integer' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(1, 2147483647),
        ];
        yield 'decimal' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(10, 2),
        ];
        yield 'float' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateFloatLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateFloatLiteral(10, 2, -307, 308),
        ];
        yield 'hex' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateHexLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateHexLiteral(1, 16),
        ];
        yield 'binary' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateBinaryLiteral(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateBinaryLiteral(1, 64),
        ];
        yield 'dollar quoted string' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateDollarQuotedString(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateDollarQuotedString(1, 255),
        ];
        yield 'parameter marker' => [
            static fn (LexicalGrammar $grammar): string => $grammar->generateParameterMarker(),
            static fn (LexicalGrammar $grammar): string => $grammar->generateParameterMarker(1, 99),
        ];
    }

    /**
     * @param list<int> $choices
     */
    #[DataProvider('providerGeneratedStringLiteral')]
    public function testGeneratesEveryStringLiteralStrategy(array $choices, string $expected): void
    {
        $faker = new class ($choices) extends \Faker\Generator {
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
            (new LexicalGrammar($faker, 'pg-17.2', true))->realize(['SCONST']),
        );
    }

    /**
     * @return iterable<string, array{list<int>, string}>
     */
    public static function providerGeneratedStringLiteral(): iterable
    {
        yield 'escape string' => [[0], "E'a\\\\b'"];
        yield 'dollar quoted string' => [[1], '$$ABORT ABORT ? $$'];
        yield 'combined arm value zero' => [[2, 0], "'ABORT ABORT'"];
        yield 'combined arm value one' => [[2, 1], "'ABORT ABORT'"];
        yield 'quote escaping' => [[2, 2], "'a''b'"];
        yield 'random body' => [[2, 3], "''"];
    }

    public function testDollarQuotedStringIncludesTheGeneratedMaximumLengthSuffix(): void
    {
        $faker = new class () extends \Faker\Generator {
            /**
             * @param mixed $min
             * @param mixed $max
             *
             * @throws UnexpectedValueException When the bound is not an integer
             */
            #[Override]
            public function numberBetween($min = 0, $max = 2147483647): int
            {
                if ($min === 0 && $max === 3) {
                    return 1;
                }
                if ($min === 0 && $max === 12) {
                    return 12;
                }
                if (!is_int($min)) {
                    throw new UnexpectedValueException();
                }

                return $min;
            }
        };

        self::assertSame(
            '$$ABORT ABORT ? aaaaaaaaaaaa$$',
            (new LexicalGrammar($faker, 'pg-17.2', true))->realize(['SCONST']),
        );
    }

    public function testTokenizesAllProblematicLiteralAndOperatorFamilies(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');
        $sql = <<<'SQL'
SELECT "values", 'a''b', E'a\b', $$FROM ?$$, $tag$WHERE$tag$, B'101', X'af', $1, data ?| tags
/* UPDATE */ -- DELETE
FROM items
SQL;

        self::assertSame([
            'SELECT', 'IDENT', ',', 'SCONST', ',', 'SCONST', ',', 'SCONST', ',', 'SCONST', ',', 'BCONST', ',',
            'XCONST', ',', 'PARAM', ',', 'DATA_P', 'Op', 'IDENT', 'FROM', 'IDENT',
        ], $lexical->tokenize($sql));
    }

    public function testAppliesParserLookaheadOnlyInItsVersionedContext(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');

        self::assertSame(['WITH_LA', 'TIME'], $lexical->tokenize('WITH TIME'));
        self::assertSame(['WITH', 'RETURNS'], $lexical->tokenize('WITH RETURNS'));
    }

    public function testNormalizeLookaheadSettlesDerivedTokensFromTheirFollowers(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');

        self::assertSame(
            ['WITH', 'IDENT', 'WITH_LA', 'TIME', 'FORMAT', 'IDENT', 'FORMAT_LA', 'JSON'],
            $lexical->normalizeLookahead([
                'WITH_LA', 'IDENT', 'WITH', 'TIME', 'FORMAT_LA', 'IDENT', 'FORMAT', 'JSON',
            ]),
        );
    }

    public function testTokenizesCommentsAdjacentToOperators(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');
        $sql = "SELECT a=/* outer /* inner */ outer */b, c/-- line\nd, e///* block */f";

        self::assertSame(
            ['SELECT', 'IDENT', '=', 'IDENT', ',', 'IDENT', '/', 'IDENT', ',', 'IDENT', 'Op', 'IDENT'],
            $lexical->tokenize($sql),
        );
    }

    public function testRealizesLookaheadTokenWithRequiredFollower(): void
    {
        $lexical = new LexicalGrammar(Factory::create(), 'pg-17.2');
        $sql = $lexical->realize(['WITH_LA', 'TIME', 'ZONE']);

        self::assertSame(['WITH_LA', 'TIME', 'ZONE'], $lexical->tokenize($sql));
    }

    public function testRealizationCanPlaceACommentBeforeTheFirstToken(): void
    {
        $faker = Factory::create();
        $faker->seed(20260815);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');
        $statements = array_map(
            static fn (int $iteration): string => $lexical->realize(['SELECT', 'IDENT']),
            range(1, 32),
        );

        self::assertNotEmpty(array_filter(
            $statements,
            static fn (string $sql): bool => preg_match('/^\s*(?:--|\/\*)/', $sql) === 1,
        ));
    }

    public function testRejectsLookaheadTokenWithoutRequiredFollower(): void
    {
        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Expected: ["WITH_LA","RETURNS"]');

        (new LexicalGrammar(Factory::create(), 'pg-17.2'))->realize(['WITH_LA', 'RETURNS']);
    }
    /**
     * @param Closure(LexicalGrammar): string $write
     */
    #[DataProvider('providerLexemeAndSpelling')]
    public function testEachLexemeIsWrittenInTheSpellingPostgreSqlReadsItFrom(Closure $write, string $pattern): void
    {
        $faker = Factory::create();
        $faker->seed(20260827);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertMatchesRegularExpression($pattern, $write($lexical));
    }

    /**
     * @return iterable<string, array{Closure(LexicalGrammar): string, string}>
     */
    public static function providerLexemeAndSpelling(): iterable
    {
        yield 'a hex literal' => [
            static fn (LexicalGrammar $l): string => $l->generateHexLiteral(),
            "/^X'[0-9a-fA-F]{1,16}'\$/",
        ];
        yield 'a binary literal' => [
            static fn (LexicalGrammar $l): string => $l->generateBinaryLiteral(),
            "/^B'[01]{1,64}'\$/",
        ];
        yield 'a dollar-quoted string' => [
            static fn (LexicalGrammar $l): string => $l->generateDollarQuotedString(),
            '/^\$\$.{1,255}\$\$$/s',
        ];
        yield 'a parameter marker' => [
            static fn (LexicalGrammar $l): string => $l->generateParameterMarker(),
            '/^\$([1-9]|[1-9]\d)$/',
        ];
    }

    /**
     * @param Closure(LexicalGrammar, int): string $write
     */
    #[DataProvider('providerBoundedLexeme')]
    public function testEachLexemeFillsExactlyTheLengthItIsAskedFor(Closure $write, int $length, string $pattern): void
    {
        $faker = Factory::create();
        $faker->seed(20260827);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');

        self::assertMatchesRegularExpression($pattern, $write($lexical, $length));
    }

    /**
     * @return iterable<string, array{Closure(LexicalGrammar, int): string, int, string}>
     */
    public static function providerBoundedLexeme(): iterable
    {
        yield 'a hex literal' => [
            static fn (LexicalGrammar $l, int $n): string => $l->generateHexLiteral($n, $n),
            5,
            "/^X'[0-9a-fA-F]{5}'\$/",
        ];
        yield 'a binary literal' => [
            static fn (LexicalGrammar $l, int $n): string => $l->generateBinaryLiteral($n, $n),
            7,
            "/^B'[01]{7}'\$/",
        ];
        yield 'a dollar-quoted string' => [
            static fn (LexicalGrammar $l, int $n): string => $l->generateDollarQuotedString($n, $n),
            9,
            '/^\$\$.{9}\$\$$/s',
        ];
    }

    public function testAParameterMarkerIsNumberedWithinTheRangeItIsGiven(): void
    {
        $faker = Factory::create();
        $faker->seed(20260827);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');
        $numbers = array_map(
            static fn (int $draw): int => (int) substr($lexical->generateParameterMarker(3, 5), 1),
            range(1, 200),
        );
        sort($numbers);

        self::assertSame([3, 4, 5], array_values(array_unique($numbers)));
    }
}
