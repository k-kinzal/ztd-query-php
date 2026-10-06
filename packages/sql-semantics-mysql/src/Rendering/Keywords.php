<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rendering;

use SqlParser\Resource\VersionRegistry;
use SqlSemantics\Contract\GrammarRelease;

/**
 * The words the MySQL lexer of one release can read as something other than an identifier.
 *
 * Rule: MYSQL-KEYWORDS-001. A word is unusable for a bare name when the
 * release lists it as a keyword or as a function name (a function name is a
 * keyword when a parenthesis follows, and under IGNORE_SPACE even after
 * spaces), or when it starts with an underscore, because `_charset` words
 * are character set introducers. The table is the keyword artifact the
 * language profile pins. Treating every such word as reserved, including
 * those a grammar position would accept bare, makes a bare spelling
 * independent of the position it is written at.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/keywords.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Keywords
{
    /**
     * @var array<string, array<string, true>>
     */
    private static array $words = [];

    /**
     * @param GrammarRelease $release The grammar release whose keyword artifact is read
     */
    public function __construct(private readonly GrammarRelease $release)
    {
    }

    /**
     * Tells whether a word cannot be written as a bare name, compared without regard to ASCII case.
     */
    public function reserved(string $word): bool
    {
        if (!isset(self::$words[$this->release->value])) {
            $words = [];
            $tables = require (new VersionRegistry())->resolve('mysql', $this->release->value)->keywordPath;
            foreach (is_array($tables) ? $tables : [] as $table) {
                foreach (is_array($table) ? array_keys($table) : [] as $keyword) {
                    $words[(string) $keyword] = true;
                }
            }
            self::$words[$this->release->value] = $words;
        }

        return ($word[0] ?? '') === '_' || isset(self::$words[$this->release->value][strtr($word, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')]);
    }
}
