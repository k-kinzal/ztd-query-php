<?php

declare(strict_types=1);

namespace Tests\Fixture\SqlFaker;

use Faker\Generator;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\Sqlite\SqliteTerminalRealizer;
use SqlFaker\Sqlite\SqliteTokenizer;

/**
 * Builds SQLite terminal realizers over a catalog small enough to read.
 *
 * A realizer needs a catalog, a tokenizer and a keyword table before it can
 * write anything at all, and a test about which spelling it chooses has no
 * interest in any of them. They are assembled here so that such a test is only
 * about the choice.
 */
final class SqliteRealizers
{
    /**
     * Answers a realizer that writes terminals from their names.
     *
     * @param Generator $faker Source of the choices realization makes
     *
     * @return SqliteTerminalRealizer A realizer allowed to write without a witness
     */
    public static function synthetic(Generator $faker): SqliteTerminalRealizer
    {
        return new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT', 'select']],
            'sqlite-3.47.2',
            true,
        );
    }

    /**
     * Answers a realizer that only replays catalogued examples.
     *
     * @param Generator $faker Source of the choices realization makes
     *
     * @return SqliteTerminalRealizer A realizer that writes only what the catalog witnesses
     */
    public static function witnessed(Generator $faker): SqliteTerminalRealizer
    {
        return new SqliteTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [
                    'ID' => [
                        ['id' => 'id.bare', 'sql' => 'users', 'tokens' => ['ID'], 'units' => ['identifier']],
                        ['id' => 'id.other', 'sql' => 'orders', 'tokens' => ['ID'], 'units' => ['identifier']],
                    ],
                    '@TRIVIA' => [
                        ['id' => 'trivia.space', 'sql' => ' ', 'tokens' => [], 'units' => ['trivia']],
                        ['id' => 'trivia.comment', 'sql' => '/* c */', 'tokens' => [], 'units' => ['trivia']],
                    ],
                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => ['identifier', 'trivia'],
                    'witnessed' => ['identifier' => 'id.bare', 'trivia' => 'trivia.space'],
                    'excluded' => [],
                ],
            ]),
            new SqliteTokenizer(['SELECT' => 'SELECT']),
            ['SELECT' => ['SELECT', 'select']],
            'sqlite-3.47.2',
            false,
        );
    }
}
