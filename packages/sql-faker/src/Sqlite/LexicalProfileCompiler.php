<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use RuntimeException;

/**
 * Extracts SQLite's keyword-token table from tool/mkkeywordhash.c.
 */
final class LexicalProfileCompiler
{
    /**
     * @return array<string, list<string>>
     *
     * @throws RuntimeException When the upstream source declares no keywords
     */
    public function compile(string $source): array
    {
        preg_match_all(
            '/\{\s*"([A-Z_]+)"\s*,\s*"TK_([A-Z][A-Z0-9_]*)"\s*,/',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        /** @var array<string, list<string>> $keywords */
        $keywords = [];
        foreach ($matches as $match) {
            $keywords[$match[2]][] = $match[1];
        }

        if ($keywords === []) {
            throw new RuntimeException('SQLite keyword table was empty.');
        }

        ksort($keywords);

        return $keywords;
    }
}
