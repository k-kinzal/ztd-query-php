<?php

declare(strict_types=1);

namespace Tests\Fixture\SqlFaker;

use Faker\Generator;
use SqlFaker\Grammar\LexicalCatalog;
use SqlFaker\PostgreSql\PgLookahead;
use SqlFaker\PostgreSql\PgTerminalRealizer;
use SqlFaker\PostgreSql\PgTokenizer;

/**
 * Builds PostgreSQL terminal realizers over a catalog small enough to read.
 *
 * A realizer needs a catalog, a tokenizer, a lookahead table and a keyword
 * table before it can write anything at all, and a test about which spelling
 * it chooses has no interest in any of them. They are assembled here so that
 * such a test is only about the choice.
 */
final class PgRealizers
{
    /**
     * Answers a realizer that writes terminals from their names.
     *
     * @param Generator $faker Source of the choices realization makes
     *
     * @return PgTerminalRealizer A realizer allowed to write without a witness
     */
    public static function synthetic(Generator $faker): PgTerminalRealizer
    {
        return self::of($faker, true);
    }

    /**
     * Answers a realizer that only replays catalogued examples.
     *
     * @param Generator $faker Source of the choices realization makes
     *
     * @return PgTerminalRealizer A realizer that writes only what the catalog witnesses
     */
    public static function witnessed(Generator $faker): PgTerminalRealizer
    {
        return self::of($faker, false);
    }

    /**
     * Answers a realizer over the shared catalog.
     *
     * @param Generator $faker Source of the choices realization makes
     * @param bool $allowSynthetic Whether terminals may be written without a witness
     *
     * @return PgTerminalRealizer The realizer
     */
    public static function of(Generator $faker, bool $allowSynthetic): PgTerminalRealizer
    {
        $lookahead = new PgLookahead(['NOT' => ['token' => 'NOT_LA', 'followed_by' => ['NULL_P']]]);

        return new PgTerminalRealizer(
            $faker,
            new LexicalCatalog(self::catalogue()),
            new PgTokenizer(['SELECT' => 'SELECT', 'VALUES' => 'VALUES'], $lookahead),
            $lookahead,
            ['SELECT' => ['SELECT', 'select'], 'NOT' => ['NOT'], 'ANALYZE' => ['ANALYZE', 'ANALYSE']],
            'pg-17.2',
            $allowSynthetic,
        );
    }

    /**
     * Answers the catalogue every realizer built here is over.
     *
     * @return array<string, mixed> The catalogue, as a compiled profile spells it
     */
    public static function catalogue(): array
    {
        return [
            'source' => ['engine' => 'official', 'entrypoint' => 'lexer'],
            'terminals' => [
                'TERMINAL' => [
                    ['id' => 'terminal.bare', 'sql' => 'users', 'tokens' => ['TOKENS'], 'units' => ['identifier']],
                    ['id' => 'terminal.other', 'sql' => 'orders', 'tokens' => ['TOKENS'], 'units' => ['identifier']],
                ],
                '@TRIVIA' => [
                    ['id' => 'trivia.space', 'sql' => ' ', 'tokens' => [], 'units' => ['trivia']],
                    ['id' => 'trivia.comment', 'sql' => '/* c */', 'tokens' => [], 'units' => ['trivia']],
                ],
                'NOTHING' => [
                    ['id' => 'nothing.empty', 'sql' => '', 'tokens' => [], 'units' => ['nothing']],
                ],
            ],
            'terminal_exclusions' => [],
            'coverage' => [
                'units' => ['identifier', 'trivia', 'nothing'],
                'witnessed' => [
                    'identifier' => 'terminal.bare',
                    'trivia' => 'trivia.space',
                    'nothing' => 'nothing.empty',
                ],
                'excluded' => [],
            ],
        ];
    }
}
