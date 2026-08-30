<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Lexical;

use RuntimeException;

/**
 * Extracts PostgreSQL's keyword-token table from kwlist.h.
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
            '/PG_KEYWORD\("([a-z_]+)",\s*([A-Z][A-Z0-9_]*)\s*,/',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        /** @var array<string, list<string>> $keywords */
        $keywords = [];
        foreach ($matches as $match) {
            $keywords[$match[2]][] = strtoupper($match[1]);
        }

        if ($keywords === []) {
            throw new RuntimeException('PostgreSQL keyword table was empty.');
        }

        ksort($keywords);

        return $keywords;
    }
}
