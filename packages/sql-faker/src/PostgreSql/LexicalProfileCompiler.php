<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

use RuntimeException;
use SqlFaker\Grammar\Lexical\RegistrationTable;

/**
 * Extracts PostgreSQL's keyword-token table from kwlist.h.
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
        $matches = (new RegistrationTable())->entries(
            $source,
            '/PG_KEYWORD\(\s*"([a-z_]+)"\s*,\s*([A-Z][A-Z0-9_]*)\s*,\s*[A-Z_]+\s*,\s*[A-Z_]+\s*\)/'
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
