<?php

declare(strict_types=1);

namespace Tests\Fixture\SqlFaker;

use Faker\Generator;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\MySql\MySqlTerminalRealizer;
use SqlFaker\MySql\MySqlTokenizer;

/**
 * Builds MySQL terminal realizers over a catalog small enough to read.
 *
 * A realizer needs a catalog, a tokenizer and two spelling tables before it
 * can write anything at all, and a test about which spelling it chooses has no
 * interest in any of them. They are assembled here so that such a test is only
 * about the choice.
 */
final class MySqlRealizers
{
    /**
     * Answers a realizer that writes terminals from their names.
     *
     * @param Generator $faker Source of the choices realization makes
     *
     * @return MySqlTerminalRealizer A realizer allowed to write without a witness
     */
    public static function synthetic(Generator $faker): MySqlTerminalRealizer
    {
        return new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [],
                'terminal_exclusions' => [],
                'coverage' => ['units' => [], 'witnessed' => [], 'excluded' => []],
            ]),
            new MySqlTokenizer(['||' => 'OR_OR_SYM', 'SELECT' => 'SELECT_SYM'], [], true),
            ['SELECT_SYM' => ['SELECT', 'select']],
            ['COUNT_SYM' => ['COUNT']],
            'mysql-8.4.7',
            true,
        );
    }

    /**
     * Answers a realizer that only replays catalogued examples.
     *
     * @param Generator $faker Source of the choices realization makes
     *
     * @return MySqlTerminalRealizer A realizer that writes only what the catalog witnesses
     */
    public static function witnessed(Generator $faker): MySqlTerminalRealizer
    {
        return new MySqlTerminalRealizer(
            $faker,
            new LexicalCatalog([
                'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
                'terminals' => [
                    'IDENT' => [
                        ['id' => 'ident.bare', 'sql' => 'users', 'tokens' => ['IDENT'], 'units' => ['identifier']],
                        ['id' => 'ident.other', 'sql' => 'orders', 'tokens' => ['IDENT'], 'units' => ['identifier']],
                    ],
                    '@TRIVIA' => [
                        ['id' => 'trivia.space', 'sql' => ' ', 'tokens' => [], 'units' => ['trivia']],
                        ['id' => 'trivia.comment', 'sql' => '/* c */', 'tokens' => [], 'units' => ['trivia']],
                    ],
                ],
                'terminal_exclusions' => [],
                'coverage' => [
                    'units' => ['identifier', 'trivia'],
                    'witnessed' => ['identifier' => 'ident.bare', 'trivia' => 'trivia.space'],
                    'excluded' => [],
                ],
            ]),
            new MySqlTokenizer(['SELECT' => 'SELECT_SYM'], [], false),
            ['SELECT_SYM' => ['SELECT', 'select']],
            ['COUNT_SYM' => ['COUNT']],
            'mysql-8.4.7',
            false,
        );
    }
}
