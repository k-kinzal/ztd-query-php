<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Closure;
use Faker\Factory;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionParameter;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Grammar\SqlVersion;
use SqlFaker\Grammar\TokenJoiner;
use SqlFaker\PostgreSql\LexicalGrammar;
use UnexpectedValueException;

#[CoversClass(LexicalGrammar::class)]
#[CoversClass(RandomStringGenerator::class)]
#[CoversClass(TokenJoiner::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(SqlVersion::class)]
final class LexicalGrammarTest extends TestCase
{
    public function testGeneratesPublicProviderLexemesThroughDialectGrammar(): void
    {
        $faker = Factory::create();
        $faker->seed(12345);
        $lexical = new LexicalGrammar($faker, 'pg-17.2');
        $sql = implode(' ', [
            $lexical->generateQuotedIdentifier(3, 3),
            $lexical->generateStringLiteral(3, 3),
            $lexical->generateIntegerLiteral(10, 10),
            $lexical->generateDecimalLiteral(4, 2),
            $lexical->generateFloatLiteral(4, 2, 2, 2),
            $lexical->generateHexLiteral(4, 4),
            $lexical->generateBinaryLiteral(4, 4),
            $lexical->generateDollarQuotedString(3, 3),
            $lexical->generateParameterMarker(2, 2),
        ]);

        self::assertSame([
            'IDENT', 'SCONST', 'ICONST', 'FCONST', 'FCONST', 'XCONST', 'BCONST', 'SCONST', 'PARAM',
        ], $lexical->tokenize($sql));
    }

    /**
     * @param Closure(LexicalGrammar): string $generate
     * @param list<int> $expected
     */
    #[DataProvider('providerPublicLexemeDefaults')]
    public function testPublicLexemeDefaultBounds(Closure $generate, string $method, array $expected): void
    {
        self::assertNotSame('', $generate(new LexicalGrammar(Factory::create(), 'pg-17.2')));
        self::assertSame(
            $expected,
            array_map(
                static fn (ReflectionParameter $parameter): mixed => $parameter->getDefaultValue(),
                (new ReflectionMethod(LexicalGrammar::class, $method))->getParameters(),
            ),
        );
    }

    /** @return iterable<string, array{Closure(LexicalGrammar): string, string, list<int>}> */
    public static function providerPublicLexemeDefaults(): iterable
    {
        yield 'quoted identifier' => [static fn (LexicalGrammar $grammar): string => $grammar->generateQuotedIdentifier(), 'generateQuotedIdentifier', [1, 63]];
        yield 'string' => [static fn (LexicalGrammar $grammar): string => $grammar->generateStringLiteral(), 'generateStringLiteral', [1, 255]];
        yield 'integer' => [static fn (LexicalGrammar $grammar): string => $grammar->generateIntegerLiteral(), 'generateIntegerLiteral', [1, 2147483647]];
        yield 'decimal' => [static fn (LexicalGrammar $grammar): string => $grammar->generateDecimalLiteral(), 'generateDecimalLiteral', [10, 2]];
        yield 'float' => [static fn (LexicalGrammar $grammar): string => $grammar->generateFloatLiteral(), 'generateFloatLiteral', [10, 2, -307, 308]];
        yield 'hex' => [static fn (LexicalGrammar $grammar): string => $grammar->generateHexLiteral(), 'generateHexLiteral', [1, 16]];
        yield 'binary' => [static fn (LexicalGrammar $grammar): string => $grammar->generateBinaryLiteral(), 'generateBinaryLiteral', [1, 64]];
        yield 'dollar quoted string' => [static fn (LexicalGrammar $grammar): string => $grammar->generateDollarQuotedString(), 'generateDollarQuotedString', [1, 255]];
        yield 'parameter marker' => [static fn (LexicalGrammar $grammar): string => $grammar->generateParameterMarker(), 'generateParameterMarker', [1, 99]];
    }

    /** @param list<int> $choices */
    #[DataProvider('providerGeneratedStringLiteral')]
    public function testGeneratesEveryStringLiteralStrategy(array $choices, string $expected): void
    {
        $faker = new class ($choices) extends \Faker\Generator {
            private int $call = 0;

            /** @param list<int> $choices */
            public function __construct(private readonly array $choices)
            {
                parent::__construct();
            }

            /**
             * @param mixed $min
             * @param mixed $max
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

    /** @return iterable<string, array{list<int>, string}> */
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

    public function testNormalizesDerivedLookaheadTokensFromTheirFollowers(): void
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
}
