<?php

declare(strict_types=1);

namespace Tests\Unit\SqlFaker\MySql;

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
use SqlFaker\MySql\MySqlTerminalRealizer;
use SqlFaker\MySql\MySqlTokenizer;

#[CoversClass(MySqlTerminalRealizer::class)]
#[UsesClass(LexicalCatalog::class)]
#[UsesClass(LexicalCatalogShape::class)]
#[UsesClass(LexicalCoverageCheck::class)]
#[UsesClass(LexicalException::class)]
#[UsesClass(LexicalWitnessCheck::class)]
#[UsesClass(LexicalWitnessShape::class)]
#[UsesClass(MySqlTokenizer::class)]
#[UsesClass(RandomStringGenerator::class)]
#[UsesClass(RandomCharacters::class)]
#[UsesClass(\SqlFaker\MySql\MySqlQuoting::class)]
final class MySqlTerminalRealizerTest extends TestCase
{
    public function testRealizeReplaysACataloguedExample(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'IDENT' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], false),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            false,
        );

        self::assertSame(['users', ['IDENT']], $realizer->realize('IDENT'));
    }

    public function testRealizeReportsATerminalTheCatalogDoesNotWitness(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'IDENT' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], false),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            false,
        );

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Unsupported MySQL terminal for mysql-8.4.7: NOT_A_TERMINAL');

        $realizer->realize('NOT_A_TERMINAL');
    }

    public function testRealizeAcceptsARequestedLexemeTheCatalogWitnesses(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'IDENT' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], false),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            false,
        );

        self::assertSame(['users', ['IDENT']], $realizer->realize('IDENT', 'users'));
    }

    public function testRealizeWitnessedReplaysTheExampleText(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'IDENT' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], false),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            false,
        );

        self::assertSame(['users', ['IDENT']], $realizer->realizeWitnessed('IDENT'));
    }

    public function testSupportsFollowsTheCatalog(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'IDENT' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], false),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            false,
        );

        self::assertTrue($realizer->supports('IDENT'));
        self::assertFalse($realizer->supports('NOT_A_TERMINAL'));
    }

    public function testRealizeRequestedRejectsALexemeTheCatalogDoesNotWitness(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'IDENT' => [
                    ['id' => 'identifier.0', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => ['identifier']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier'],
                'witnessed' => ['identifier' => 'identifier.0'],
                'excluded' => [],
            ],
        ];

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], false),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            false,
        );

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('MySQL lexical catalog has no IDENT witness for: other');

        $realizer->realizeRequested('IDENT', 'other');
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

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], false),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            false,
        );

        self::assertSame(['SELECT', ['SELECT_SYM']], $realizer->realizeFixed('SELECT_SYM'));
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

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], false),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
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

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        self::assertTrue($realizer->supports('NOT_A_TERMINAL'));
    }

    public function testRealizeSkipsTheTerminalsThatStandForNoText(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        self::assertSame([null, []], $realizer->realize('END_OF_INPUT'));
        self::assertSame([null, []], $realizer->realize('GRAMMAR_SELECTOR_EXPR'));
    }

    public function testRealizeSyntheticWritesTerminalsNoCatalogWitnesses(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        self::assertSame(['?', ['PARAM_MARKER']], $realizer->realizeSynthetic('PARAM_MARKER'));
        self::assertSame(['||', ['OR2_SYM']], $realizer->realizeSynthetic('OR2_SYM'));
        self::assertSame(['_utf8mb4', ['UNDERSCORE_CHARSET']], $realizer->realizeSynthetic('UNDERSCORE_CHARSET'));
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

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        self::assertSame(['?', ['PARAM_MARKER']], $realizer->realizeRequested('PARAM_MARKER', '?'));
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

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        $this->expectException(LexicalException::class);
        $this->expectExceptionMessage('Requested MySQL lexeme does not realize PARAM_MARKER: users');

        $realizer->realizeRequested('PARAM_MARKER', 'users');
    }

    public function testIdentifierCannotCollideWithAKeyword(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        self::assertStringStartsWith('_', $realizer->identifier());
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

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
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

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        self::assertSame('', $realizer->optionalTrivia());
    }

    #[DataProvider('providerSyntheticTerminal')]
    public function testRealizeSyntheticWritesEveryNamedTerminalAsItsOwnKindOfToken(
        string $terminal,
        string $token,
    ): void {
        $faker = Factory::create();
        $faker->seed(1729);

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
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
            'IDENT' => 'IDENT',
            'IDENT_QUOTED' => 'IDENT_QUOTED',
            'TEXT_STRING' => 'TEXT_STRING',
            'NCHAR_STRING' => 'NCHAR_STRING',
            'DOLLAR_QUOTED_STRING_SYM' => 'DOLLAR_QUOTED_STRING_SYM',
            'NUM' => 'NUM',
            'LONG_NUM' => 'LONG_NUM',
            'ULONGLONG_NUM' => 'ULONGLONG_NUM',
            'DECIMAL_NUM' => 'DECIMAL_NUM',
            'FLOAT_NUM' => 'FLOAT_NUM',
            'HEX_NUM' => 'HEX_NUM',
            'BIN_NUM' => 'BIN_NUM',
            'LEX_HOSTNAME' => 'IDENT',
            'PARAM_MARKER' => 'PARAM_MARKER',
            'OR2_SYM' => 'OR2_SYM',
            'WITH_ROLLUP_SYM' => 'WITH_ROLLUP_SYM',
            'UNDERSCORE_CHARSET' => 'UNDERSCORE_CHARSET',
        ];
        foreach ($terminals as $terminal => $token) {
            yield $terminal => [$terminal, $token];
        }
    }

    #[DataProvider('providerFixedSyntheticTerminal')]
    public function testRealizeSyntheticWritesEveryFixedTerminalAsItsOneSpelling(
        string $terminal,
        string $lexeme,
    ): void {
        $faker = Factory::create();
        $faker->seed(1729);

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
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
            'ULONGLONG_NUM' => '18446744073709551615',
            'LEX_HOSTNAME' => 'localhost',
            'PARAM_MARKER' => '?',
            'OR2_SYM' => '||',
            'WITH_ROLLUP_SYM' => 'WITH ROLLUP',
            'UNDERSCORE_CHARSET' => '_utf8mb4',
        ];
        foreach ($spellings as $terminal => $lexeme) {
            yield $terminal => [$terminal, $lexeme];
        }
    }

    #[DataProvider('providerSyntheticSpelling')]
    public function testSyntheticSpellingWritesTheOperatorATerminalStandsFor(
        string $terminal,
        string $expected,
    ): void {
        $faker = Factory::create();
        $faker->seed(1729);

        $catalogue = [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [],
            'terminal_exclusions' => [],
            'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
        ];

        $realizer = new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog($catalogue),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        self::assertSame(
            $expected,
            $realizer->syntheticSpelling($terminal),
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function providerSyntheticSpelling(): iterable
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
        yield 'OR2_SYM' => ['OR2_SYM', '||'];
        yield 'NOT2_SYM' => ['NOT2_SYM', 'NOT'];
        yield 'SET_VAR' => ['SET_VAR', ':='];
        yield 'JSON_SEPARATOR_SYM' => ['JSON_SEPARATOR_SYM', '->'];
        yield 'JSON_UNQUOTED_SEPARATOR_SYM' => ['JSON_UNQUOTED_SEPARATOR_SYM', '->>'];
        yield 'NEG' => ['NEG', '-'];
        yield 'a keyword terminal drops its suffix' => ['SELECT_SYM', 'SELECT'];
        yield 'a terminal without the suffix stands for itself' => ['IDENT', 'IDENT'];
    }

    public function testRealizeWitnessedSelectsFromBothConfiguredExamples(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
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
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT', 'select']],
            [],
            'mysql-8.4.7',
            false,
        );

        $results = array_map(static fn (): array => $realizer->realizeFixed('SELECT_SYM'), range(1, 64));

        self::assertContains(['SELECT', ['SELECT_SYM']], $results);
        self::assertContains(['select', ['SELECT_SYM']], $results);
        self::assertSame([], array_diff(array_column($results, 0), ['SELECT', 'select']));
        self::assertSame(array_fill(0, 64, ['SELECT_SYM']), array_column($results, 1));
    }

    public function testTriviaSelectsOnlyConfiguredSeparators(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
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
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
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
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->stringLiteral(), range(1, 128));
        self::assertSame(array_fill(0, 128, ['TEXT_STRING']), array_map($tokenizer->tokenize(...), $samples));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains(substr($sql, 1, -1), "''")));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains($sql, '\\')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains($sql, 'SELECT')));
    }

    public function testQuotedIdentifierProducesEscapedIdentifiers(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->quotedIdentifier(), range(1, 256));
        self::assertSame(array_fill(0, 256, ['IDENT_QUOTED']), array_map($tokenizer->tokenize(...), $samples));
        self::assertContains('`select`', $samples);
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_contains($sql, '``')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '`_')));
    }

    public function testHexadecimalLiteralProducesBothValidSpellings(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->hexadecimalLiteral(), range(1, 128));
        self::assertSame(array_fill(0, 128, ['HEX_NUM']), array_map($tokenizer->tokenize(...), $samples));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '0x')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, "X'")));
        self::assertSame([], array_filter($samples, static fn (string $sql): bool => preg_match("/^(?:0x[0-9a-f]+|X'(?:[0-9a-f]{2})*')$/", $sql) !== 1));
    }

    public function testBinaryLiteralProducesBothValidSpellings(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->binaryLiteral(), range(1, 128));
        self::assertSame(array_fill(0, 128, ['BIN_NUM']), array_map($tokenizer->tokenize(...), $samples));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, '0b')));
        self::assertNotSame([], array_filter($samples, static fn (string $sql): bool => str_starts_with($sql, "B'")));
        self::assertSame([], array_filter($samples, static fn (string $sql): bool => preg_match("/^(?:0b[01]+|B'[01]*')$/", $sql) !== 1));
    }

    public function testDollarQuotedStringProducesOneStringToken(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            true,
        );

        $samples = array_map(static fn (): string => $realizer->dollarQuotedString(), range(1, 128));
        self::assertSame(array_fill(0, 128, ['DOLLAR_QUOTED_STRING_SYM']), array_map($tokenizer->tokenize(...), $samples));
    }

    public function testRealizeFixedUsesFunctionSpellings(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT']],
            ['COUNT_SYM' => ['COUNT']],
            'mysql-8.4.7',
            false,
        );

        self::assertSame(['COUNT', ['COUNT_SYM']], $realizer->realizeFixed('COUNT_SYM'));
    }

    public function testRealizeFixedTokenizesAnUnconfiguredTerminal(): void
    {
        $faker = Factory::create();
        $faker->seed(1729);
        $tokenizer = new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], true);
        $realizer = new MySqlTerminalRealizer(
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
            ['SELECT_SYM' => ['SELECT']],
            [],
            'mysql-8.4.7',
            false,
        );

        self::assertSame(['OTHER', ['IDENT']], $realizer->realizeFixed('OTHER'));
    }
}
