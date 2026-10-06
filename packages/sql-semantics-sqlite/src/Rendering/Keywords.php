<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rendering;

use SqlParser\Resource\VersionRegistry;

/**
 * The words the SQLite tokenizer reads as keywords.
 *
 * Every keyword is treated as unusable for a bare name, including those the
 * parser would accept as a fallback identifier, so a bare spelling never
 * depends on the position it is written at.
 *
 * @visibility SqlSemantics
 */
final class Keywords
{
    /**
     * @var array<string, true>|null
     */
    private static ?array $words = null;

    /**
     * Tells whether a word is a keyword, compared without regard to ASCII case.
     */
    public function reserved(string $word): bool
    {
        if (self::$words === null) {
            $words = [];
            $tables = require (new VersionRegistry())->resolve('sqlite', 'sqlite-3.47.2')->keywordPath;
            if (is_array($tables)) {
                foreach ($tables as $table) {
                    if (is_array($table)) {
                        foreach (array_keys($table) as $keyword) {
                            $words[strtoupper((string) $keyword)] = true;
                        }
                    }
                }
            }
            self::$words = $words;
        }

        return isset(self::$words[strtr($word, 'abcdefghijklmnopqrstuvwxyz', 'ABCDEFGHIJKLMNOPQRSTUVWXYZ')]);
    }
}
