<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\Sqlite;

use Faker\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Grammar\LexicalCatalogShape;
use SqlFaker\Grammar\LexicalCoverageCheck;
use SqlFaker\Grammar\LexicalException;
use SqlFaker\Grammar\LexicalWitnessCheck;
use SqlFaker\Grammar\LexicalWitnessShape;
use SqlFaker\Grammar\RandomCharacters;
use SqlFaker\Grammar\RandomStringGenerator;
use SqlFaker\Sqlite\SqliteTerminalRealizer;
use SqlFaker\Sqlite\SqliteTokenizer;

#[CoversClass(SqliteTerminalRealizer::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(SqliteTokenizer::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(\SqlFaker\Sqlite\SqliteQuoting::class)]
final class SqliteTerminalRealizerTest extends TestCase
{
    public function testRealizeReplaysACataloguedExample(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TK_ID'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            false,
        );

        self::assertSame(['users', ['ID']], $realizer->realize('TERMINAL'));
    }

    public function testRealizeWritesTheStrictTableOptionAsAnIdentifier(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TK_ID'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            false,
        );

        self::assertSame(['STRICT', ['ID']], $realizer->realize(SqliteTerminalRealizer::STRICT_TABLE_OPTION));
    }

    public function testRealizeReportsATerminalTheCatalogDoesNotWitness(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TK_ID'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            false,
        );

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported SQLite terminal for sqlite-3.47.2: NOT_A_TERMINAL');

        $realizer->realize('NOT_A_TERMINAL');
    }

    public function testRealizeWitnessedStripsThePrefixesTheUpstreamLexerUses(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TK_ID'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            false,
        );

        self::assertSame(['users', ['ID']], $realizer->realizeWitnessed('TERMINAL'));
    }

    public function testSupportsFollowsTheCatalogAndTheStrictOption(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TK_ID'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            false,
        );

        self::assertTrue($realizer->supports('TERMINAL'));
        self::assertTrue($realizer->supports(SqliteTerminalRealizer::STRICT_TABLE_OPTION));
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
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['TK_ID'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            false,
        );

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('SQLite lexical catalog has no TERMINAL witness for: other');

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

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            false,
        );

        self::assertSame(['SELECT', ['SELECT']], $realizer->realizeFixed('SELECT'));
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

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
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

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        self::assertTrue($realizer->supports('NOT_A_TERMINAL'));
    }

    public function testRealizeSyntheticWritesTheOperatorTerminals(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        self::assertSame(['(', ['LP']], $realizer->realizeSynthetic('LP'));
        self::assertSame(['||', ['CONCAT']], $realizer->realizeSynthetic('CONCAT'));
        self::assertSame(['->', ['PTR']], $realizer->realizeSynthetic('PTR'));
    }

    public function testRealizeSyntheticWritesTheSeparatedNumberLiteral(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        self::assertSame(['1_0', ['QNUMBER']], $realizer->realizeSynthetic('QNUMBER'));
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

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        self::assertSame(['(', ['LP']], $realizer->realizeRequested('LP', '('));
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

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Requested SQLite lexeme does not realize LP: users');

        $realizer->realizeRequested('LP', 'users');
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

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
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

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        self::assertSame('', $realizer->optionalTrivia());
    }

    #[DataProvider('providerOperatorTerminal')]
    public function testRealizeSyntheticWritesEveryOperatorAsItsOwnPunctuation(
        string $terminal,
        string $punctuation,
    ): void {
        $faker = Factory::create();
        $faker->seed(1729);

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        self::assertSame([$punctuation, [$terminal]], $realizer->realizeSynthetic($terminal));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerOperatorTerminal(): iterable
    {

        $operators = [
            'LP' => '(', 'RP' => ')', 'SEMI' => ';', 'COMMA' => ',', 'DOT' => '.',
            'EQ' => '=', 'LT' => '<', 'LE' => '<=', 'GT' => '>', 'GE' => '>=', 'NE' => '<>',
            'PLUS' => '+', 'MINUS' => '-', 'STAR' => '*', 'SLASH' => '/', 'REM' => '%',
            'BITAND' => '&', 'BITOR' => '|', 'BITNOT' => '~', 'LSHIFT' => '<<', 'RSHIFT' => '>>',
            'CONCAT' => '||', 'PTR' => '->',
        ];
        foreach ($operators as $terminal => $punctuation) {
            yield $terminal => [$terminal, $punctuation];
        }
    }

    #[DataProvider('providerSyntheticTerminal')]
    public function testRealizeSyntheticWritesEveryNamedTerminalAsItsOwnKindOfToken(
        string $terminal,
        string $token,
    ): void {
        $faker = Factory::create();
        $faker->seed(1729);

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        self::assertSame([$token], $realizer->realizeSynthetic($terminal)[1]);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSyntheticTerminal(): iterable
    {

        $terminals = [
            'ID' => 'ID', 'id' => 'ID', 'idj' => 'ID',
            'ids' => 'STRING', 'STRING' => 'STRING',
            'BLOB' => 'BLOB',
            'number' => 'INTEGER', 'INTEGER' => 'INTEGER',
            'FLOAT' => 'FLOAT',
            'QNUMBER' => 'QNUMBER',
            'VARIABLE' => 'VARIABLE',
            'ANY' => 'ID',
        ];
        foreach ($terminals as $terminal => $token) {
            yield $terminal => [$terminal, $token];
        }
    }

    public function testRealizeSyntheticWritesTheOneNumberSqliteWillNotLexBackAsItself(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        self::assertSame(['1_0', ['QNUMBER']], $realizer->realizeSynthetic('QNUMBER'));
    }

    public function testRealizeSyntheticWritesTheWildcardAsAnIdentifierNoKeywordCollidesWith(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        self::assertSame(['_any', ['ID']], $realizer->realizeSynthetic('ANY'));
    }

    public function testRealizeWitnessedSelectsFromBothConfiguredExamples(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new SqliteTokenizer(['SELECT' => 'SELECT']);
        $realizer = new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [
                    'ID' => [
                        ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['ID'], 'units' => ['identifier']],
                        ['id' => 'identifier.1', 'sql' => 'orders', 'tokens' => ['ID'], 'units' => ['identifier']],
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
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            false,
        );

        $results = array_map(static fn (): array => $realizer->realizeWitnessed('ID'), range(1, 64));

        self::assertContains(['users', ['ID']], $results);
        self::assertContains(['orders', ['ID']], $results);
        self::assertSame([], array_diff(array_column($results, 0), ['users', 'orders']));
        self::assertSame(array_fill(0, 64, ['ID']), array_column($results, 1));
    }

    public function testRealizeFixedSelectsFromBothConfiguredSpellings(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new SqliteTokenizer(['SELECT' => 'SELECT']);
        $realizer = new SqliteTerminalRealizer(
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
            ['SELECT' => ['SELECT', 'select']],
            'sqlite-3.47.2',
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
        $tokenizer = new SqliteTokenizer(['SELECT' => 'SELECT']);
        $realizer = new SqliteTerminalRealizer(
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
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
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
        $tokenizer = new SqliteTokenizer(['SELECT' => 'SELECT']);
        $realizer = new SqliteTerminalRealizer(
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
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            false,
        );

        $samples = array_map(static fn (): string => $realizer->optionalTrivia(), range(1, 64));

        self::assertContains('', $samples);
        self::assertContains(' ', $samples);
        self::assertContains('/* separator */', $samples);
        self::assertSame([], array_diff($samples, ['', ' ', '/* separator */']));
    }

    public function testStringLiteralProducesStringsThatLexAsOneToken(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new SqliteTokenizer(['SELECT' => 'SELECT']);
        $realizer = new SqliteTerminalRealizer(
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
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->stringLiteral(), range(1, 128));
        self::assertSame(array_fill(0, 128, ['STRING']), array_map($tokenizer->tokenize(...), $samples));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains(substr($sql, 1, -1), "''")));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains($sql, '\\')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains($sql, 'SELECT')));
    }

    public function testIdentifierGeneratesAllFourQuotingForms(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new SqliteTokenizer(['SELECT' => 'SELECT']);
        $realizer = new SqliteTerminalRealizer(
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
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->identifier(), range(1, 256));
        self::assertContains('select', $samples);
        self::assertContains('"select""quoted"', $samples);
        self::assertContains('`select``quoted`', $samples);
        self::assertContains('[selectquoted]', $samples);
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '_')));
        self::assertSame([], array_filter(array_map($tokenizer->tokenize(...), $samples), static fn (array $tokens): bool => $tokens !== ['ID'] && $tokens !== ['SELECT']));
    }

    public function testParameterGeneratesAllFiveAcceptedForms(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new SqliteTokenizer(['SELECT' => 'SELECT']);
        $realizer = new SqliteTerminalRealizer(
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
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->parameter(), range(1, 256));
        self::assertSame(array_fill(0, 256, ['VARIABLE']), array_map($tokenizer->tokenize(...), $samples));
        self::assertContains('?', $samples);
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => preg_match('/^\?[0-9]+$/', $sql) === 1));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, ':')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '@')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '$')));
    }

    public function testBlobLiteralGeneratesWholeBytesIncludingAnEmptyBlob(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new SqliteTokenizer(['SELECT' => 'SELECT']);
        $realizer = new SqliteTerminalRealizer(
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
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->blobLiteral(), range(1, 128));
        self::assertSame(array_fill(0, 128, ['BLOB']), array_map($tokenizer->tokenize(...), $samples));
        self::assertContains("X''", $samples);
        self::assertSame([], array_filter($samples, static fn (string $sql): bool => preg_match("/^X'(?:[0-9a-f]{2})*'$/", $sql) !== 1));
    }

    public function testRealizeFixedTokenizesAnUnconfiguredTerminal(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new SqliteTokenizer(['SELECT' => 'SELECT']);
        $realizer = new SqliteTerminalRealizer(
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
            ['SELECT' => ['SELECT']],
            'sqlite-3.47.2',
            true,
        );

        self::assertSame(['OTHER', ['ID']], $realizer->realizeFixed('OTHER'));
    }
}
