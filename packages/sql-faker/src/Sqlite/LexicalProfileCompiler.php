<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite;

use RuntimeException;
use SqlFaker\Grammar\Lexical\RegistrationTable;

/**
 * Extracts SQLite's keyword-token table from tool/mkkeywordhash.c.
 *
 * @visibility root
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
        $reader = new RegistrationTable();
        $region = str_contains($source, 'aKeywordTable[') ? $reader->body($source, 'aKeywordTable') : $source;
        $matches = $reader->entries(
            $region,
            '/\{\s*"([A-Z_]+)"\s*,\s*"TK_([A-Z][A-Z0-9_]*)"\s*,\s*[A-Z_|0-9 \t]+\s*,\s*[0-9]+\s*\}/'
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
