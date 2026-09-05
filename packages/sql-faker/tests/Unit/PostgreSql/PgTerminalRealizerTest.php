<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\PostgreSql;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\Lexical\LexicalCatalogShape;
use SqlFaker\Grammar\Lexical\LexicalCoverageCheck;
use SqlFaker\Grammar\Lexical\LexicalWitnessCheck;
use SqlFaker\Grammar\Lexical\LexicalWitnessShape;
use SqlFaker\Grammar\Lexical\RandomCharacters;
use SqlFaker\Grammar\Lexical\RandomStringGenerator;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\PostgreSql\PgLookahead;
use SqlFaker\PostgreSql\PgTerminalRealizer;
use SqlFaker\PostgreSql\PgTokenizer;

#[CoversClass(PgTerminalRealizer::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
#[UsesClass(PgLookahead::class)]
#[UsesClass(PgTokenizer::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(\SqlFaker\PostgreSql\PgQuoting::class)]
final class PgTerminalRealizerTest extends TestCase
{
    public function testRealizeReplaysACataloguedExample(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TOKENS'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT'], 'NOT' => ['NOT']],
            'pg-17.2',
            false,
        );

        self::assertSame(['users', ['TOKENS']], $realizer->realize('TERMINAL'));
    }

    public function testRealizeReportsATerminalTheCatalogDoesNotWitness(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TOKENS'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT'], 'NOT' => ['NOT']],
            'pg-17.2',
            false,
        );

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported PostgreSQL terminal for pg-17.2: NOT_A_TERMINAL');

        $realizer->realize('NOT_A_TERMINAL');
    }

    public function testRealizeWitnessedReplaysTheExampleText(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TOKENS'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT'], 'NOT' => ['NOT']],
            'pg-17.2',
            false,
        );

        self::assertSame(['users', ['TOKENS']], $realizer->realizeWitnessed('TERMINAL'));
    }

    public function testSupportsFollowsTheCatalog(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TOKENS'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT'], 'NOT' => ['NOT']],
            'pg-17.2',
            false,
        );

        self::assertTrue($realizer->supports('TERMINAL'));
        self::assertFalse($realizer->supports('NOT_A_TERMINAL'));
    }

    public function testRealizeRequestedRejectsALexemeTheCatalogDoesNotWitness(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TOKENS'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT'], 'NOT' => ['NOT']],
            'pg-17.2',
            false,
        );

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('PostgreSQL lexical catalog has no TERMINAL witness for: other');

        $realizer->realizeRequested('TERMINAL', 'other');
    }

    public function testRealizeFixedPrefersASpellingTheProfileLists(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [

            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => [],
                'witnessed' => [],
                'excluded' => [],
            ],
        ];

        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT'], 'NOT' => ['NOT']],
            'pg-17.2',
            false,
        );

        self::assertSame(['SELECT', ['SELECT']], $realizer->realizeFixed('SELECT'));
    }

    public function testRealizeFixedFollowsALookaheadSubstitutionBackToItsKeyword(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [

            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => [],
                'witnessed' => [],
                'excluded' => [],
            ],
        ];

        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT'], 'NOT' => ['NOT']],
            'pg-17.2',
            false,
        );

        self::assertSame(['NOT', ['NOT_LA']], $realizer->realizeFixed('NOT_LA'));
    }

    public function testRealizeFixedDropsTheSuffixOfATerminalTheProfileDoesNotSpell(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [

            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => [],
                'witnessed' => [],
                'excluded' => [],
            ],
        ];

        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT'], 'NOT' => ['NOT']],
            'pg-17.2',
            false,
        );

        self::assertSame('users', $realizer->realizeFixed('users_P')[0]);
    }

    public function testTriviaReplaysAWitnessedSeparator(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                '@TRIVIA' => [
                    ['id' => 'trivia.0', 'sql' => ' ', 'tokens' => [], 'units' => ['trivia']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['trivia'],
                'witnessed' => ['trivia' => 'trivia.0'],
                'excluded' => [],
            ],
        ];

        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT'], 'NOT' => ['NOT']],
            'pg-17.2',
            false,
        );

        self::assertSame(' ', $realizer->trivia());
    }

    public function testSupportsAcceptsAnythingOnceSyntheticWritingIsAllowed(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertTrue($realizer->supports('NOT_A_TERMINAL'));
    }

    public function testRealizeSkipsTheModeTerminalsThatStandForNoText(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertSame([null, []], $realizer->realize('MODE_TYPE_NAME'));
    }

    public function testRealizeSyntheticWritesTheOperatorsThatHaveTokens(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertSame(['::', ['TYPECAST']], $realizer->realizeSynthetic('TYPECAST'));
        self::assertSame(['..', ['DOT_DOT']], $realizer->realizeSynthetic('DOT_DOT'));
        self::assertSame(['<=', ['LESS_EQUALS']], $realizer->realizeSynthetic('LESS_EQUALS'));
    }

    public function testRealizeRequestedAcceptsALexemeThatReadsBackAsTheTerminal(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertSame(['::', ['TYPECAST']], $realizer->realizeRequested('TYPECAST', '::'));
    }

    public function testRealizeRequestedRejectsALexemeThatReadsBackAsSomethingElse(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Requested PostgreSQL lexeme does not realize TYPECAST: users');

        $realizer->realizeRequested('TYPECAST', 'users');
    }

    public function testQuotedIdentifierTakesTheUnicodePrefixWhenAsked(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertMatchesRegularExpression('/^U&".*"$/', $realizer->quotedIdentifier(true));
        self::assertMatchesRegularExpression('/^".*"$/', $realizer->quotedIdentifier(false));
    }

    public function testUnicodeStringLiteralTakesTheUnicodePrefix(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertMatchesRegularExpression('/^U&\'.*\'$/s', $realizer->unicodeStringLiteral());
    }

    public function testTriviaIsASingleSpaceWhenNothingIsWitnessed(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertSame(' ', $realizer->trivia());
    }

    public function testOptionalTriviaIsNothingWhenNothingIsWitnessed(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertSame('', $realizer->optionalTrivia());
    }

    #[DataProvider('providerSyntheticTerminal')]
    public function testRealizeSyntheticWritesEveryNamedTerminalAsItsOwnKindOfToken(
        string $terminal,
    ): void {
        $lookahead = new PgLookahead([]);

        $faker = Factory::create();
        $faker->seed(1729);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertSame([$terminal], $realizer->realizeSynthetic($terminal)[1]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function providerSyntheticTerminal(): iterable
    {

        $terminals = [
            'IDENT', 'UIDENT', 'SCONST', 'USCONST', 'ICONST', 'FCONST', 'BCONST', 'XCONST',
            'Op', 'PARAM', 'TYPECAST', 'DOT_DOT', 'COLON_EQUALS', 'EQUALS_GREATER',
            'NOT_EQUALS', 'LESS_EQUALS', 'GREATER_EQUALS',
        ];
        foreach ($terminals as $terminal) {
            yield $terminal => [$terminal];
        }
    }

    #[DataProvider('providerFixedSyntheticTerminal')]
    public function testRealizeSyntheticWritesEveryFixedOperatorAsItsOneSpelling(
        string $terminal,
        string $lexeme,
    ): void {
        $lookahead = new PgLookahead([]);

        $faker = Factory::create();
        $faker->seed(1729);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertSame($lexeme, $realizer->realizeSynthetic($terminal)[0]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerFixedSyntheticTerminal(): iterable
    {

        $spellings = [
            'TYPECAST' => '::',
            'DOT_DOT' => '..',
            'COLON_EQUALS' => ':=',
            'EQUALS_GREATER' => '=>',
            'LESS_EQUALS' => '<=',
            'GREATER_EQUALS' => '>=',
        ];
        foreach ($spellings as $terminal => $lexeme) {
            yield $terminal => [$terminal, $lexeme];
        }
    }

    public function testRealizeSyntheticWritesInequalityEitherWayRoundPostgresAcceptsIt(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $lookahead = new PgLookahead([]);

        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new PgTokenizer(['SELECT' => 'SELECT'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        self::assertContains($realizer->realizeSynthetic('NOT_EQUALS')[0], ['<>', '!=']);
    }

    public function testRealizeWitnessedSelectsFromBothConfiguredExamples(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [
                    'IDENT' => [
                        ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => ['identifier']],
                        ['id' => 'identifier.1', 'sql' => 'orders', 'tokens' => ['IDENT'], 'units' => ['identifier']],
                    ],
                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => ['identifier'],
                    'witnessed' => ['identifier' => 'identifier.0'],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            false,
        );

        $results = array_map(static fn (): array => $realizer->realizeWitnessed('IDENT'), range(1, 64));

        self::assertContains(['users', ['IDENT']], $results);
        self::assertContains(['orders', ['IDENT']], $results);
        self::assertSame([], array_diff(array_column($results, 0), ['users', 'orders']));
        self::assertSame(array_fill(0, 64, ['IDENT']), array_column($results, 1));
    }

    public function testRealizeFixedSelectsFromBothConfiguredSpellings(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [

                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => [],
                    'witnessed' => [],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT', 'select']],
            'pg-17.2',
            false,
        );

        $results = array_map(static fn (): array => $realizer->realizeFixed('SELECT'), range(1, 64));

        self::assertContains(['SELECT', ['SELECT']], $results);
        self::assertContains(['select', ['SELECT']], $results);
        self::assertSame([], array_diff(array_column($results, 0), ['SELECT', 'select']));
        self::assertSame(array_fill(0, 64, ['SELECT']), array_column($results, 1));
    }

    public function testTriviaSelectsOnlyConfiguredSeparators(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [
                    '@TRIVIA' => [
                        ['id' => 'trivia.0', 'sql' => ' ', 'tokens' => [], 'units' => ['trivia']],
                        ['id' => 'trivia.1', 'sql' => '/* separator */', 'tokens' => [], 'units' => ['trivia']],
                    ],
                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => ['trivia'],
                    'witnessed' => ['trivia' => 'trivia.0'],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            false,
        );

        $samples = array_map(static fn (): string => $realizer->trivia(), range(1, 64));

        self::assertContains(' ', $samples);
        self::assertContains('/* separator */', $samples);
        self::assertSame([], array_diff($samples, [' ', '/* separator */']));
    }

    public function testOptionalTriviaSelectsAbsenceOrAConfiguredSeparator(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [
                    '@TRIVIA' => [
                        ['id' => 'trivia.0', 'sql' => ' ', 'tokens' => [], 'units' => ['trivia']],
                        ['id' => 'trivia.1', 'sql' => '/* separator */', 'tokens' => [], 'units' => ['trivia']],
                    ],
                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => ['trivia'],
                    'witnessed' => ['trivia' => 'trivia.0'],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            false,
        );

        $samples = array_map(static fn (): string => $realizer->optionalTrivia(), range(1, 64));

        self::assertContains('', $samples);
        self::assertContains(' ', $samples);
        self::assertContains('/* separator */', $samples);
        self::assertSame([], array_diff($samples, ['', ' ', '/* separator */']));
    }

    public function testStringLiteralGeneratesEscapeDollarAndStandardForms(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [

                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => [],
                    'witnessed' => [],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->stringLiteral(), range(1, 256));
        self::assertSame(array_fill(0, 256, ['SCONST']), array_map($tokenizer->tokenize(...), $samples));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, "E'")));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '$')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains(substr($sql, 1, -1), "''")));
    }

    public function testStandardStringLiteralEscapesApostrophesAndIncludesKeywordText(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [

                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => [],
                    'witnessed' => [],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->standardStringLiteral(), range(1, 128));
        self::assertSame(array_fill(0, 128, ['SCONST']), array_map($tokenizer->tokenize(...), $samples));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains(substr($sql, 1, -1), "''")));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains($sql, 'SELECT')));
    }

    public function testQuotedIdentifierProducesEscapedIdentifiers(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [

                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => [],
                    'witnessed' => [],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->quotedIdentifier(false), range(1, 256));
        self::assertSame(array_fill(0, 256, ['IDENT']), array_map($tokenizer->tokenize(...), $samples));
        self::assertContains('"values"', $samples);
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains($sql, '""')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '"_')));
    }

    public function testIdentifierProducesBothBareAndQuotedIdentifiers(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [

                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => [],
                    'witnessed' => [],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->identifier(), range(1, 128));
        self::assertSame(array_fill(0, 128, ['IDENT']), array_map($tokenizer->tokenize(...), $samples));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '_')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '"')));
    }

    public function testDollarQuotedStringProducesTaggedAndUntaggedStrings(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [

                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => [],
                    'witnessed' => [],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->dollarQuotedString(), range(1, 128));
        self::assertSame(array_fill(0, 128, ['SCONST']), array_map($tokenizer->tokenize(...), $samples));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '$$')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => !str_starts_with($sql, '$$')));
        self::assertSame([], array_filter($samples, static fn (string $sql): bool => preg_match('/^(\$(?:[a-z_][a-z0-9_]*)?\$).*\1$/s', $sql) !== 1));
    }

    public function testDecimalLiteralGeneratesFractionTrailingPointAndExponentForms(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [

                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => [],
                    'witnessed' => [],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->decimalLiteral(), range(1, 128));
        self::assertSame(array_fill(0, 128, ['FCONST']), array_map($tokenizer->tokenize(...), $samples));
        self::assertContains('.5', $samples);
        self::assertContains('1.', $samples);
        self::assertContains('1e-1', $samples);
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => !in_array($sql, ['.5', '1.', '1e-1'], true)));
    }

    public function testOperatorGeneratesCommonAndUserDefinedOperators(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [

                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => [],
                    'witnessed' => [],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->operator(), range(1, 256));
        self::assertSame(array_fill(0, 256, ['Op']), array_map($tokenizer->tokenize(...), $samples));
        self::assertContains('?', $samples);
        self::assertContains('?|', $samples);
        self::assertContains('?&', $samples);
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => !in_array($sql, ['?', '?|', '?&'], true)));
    }

    public function testRandomOperatorUsesTheOperatorAlphabetAndAllSupportedLengths(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [

                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => [],
                    'witnessed' => [],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->randomOperator(), range(1, 256));
        self::assertContains(2, array_map(strlen(...), $samples));
        self::assertContains(3, array_map(strlen(...), $samples));
        self::assertContains(4, array_map(strlen(...), $samples));
        self::assertSame([], array_filter($samples, static fn (string $sql): bool => preg_match('/^[+*\/<>=~!@#%^&|`?\-]{2,4}$/', $sql) !== 1));
    }

    public function testRealizeWitnessedConvertsAnEmptyExampleToNoText(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $lookahead = new PgLookahead([]);
        $tokenizer = new PgTokenizer(['SELECT' => 'SELECT'], $lookahead);
        $realizer = new PgTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [
                    'EMPTY' => [
                        ['id' => 'identifier.0', 'sql' => '', 'tokens' => [], 'units' => ['identifier']],
                    ],
                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => ['identifier'],
                    'witnessed' => ['identifier' => 'identifier.0'],
                    'excluded' => [],
                ],
            ]),
            $tokenizer,
            $lookahead,
            ['SELECT' => ['SELECT']],
            'pg-17.2',
            false,
        );

        self::assertSame([null, []], $realizer->realizeWitnessed('EMPTY'));
    }
}
