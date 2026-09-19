<?php

declare(strict_types=1);

namespace SqlParser\PostgreSql\Source;

use RuntimeException;

/**
 * Extracts the keyword table from PostgreSQL's `src/include/parser/kwlist.h`.
 *
 * @visibility root
 */
final class KeywordList
{
    /**
     * Reads the keyword table.
     *
     * @param string $source Contents of `kwlist.h`
     *
     * @return array{keywords: array<string, string>} Terminal name by upper-cased keyword
     *
     * @throws RuntimeException When the file declares no keyword
     */
    public function parse(string $source): array
    {
        if (preg_match_all('/PG_KEYWORD\(\s*"([a-z_0-9]+)"\s*,\s*([A-Za-z_0-9]+)\s*,/', $source, $matches, PREG_SET_ORDER) === 0) {
            throw new RuntimeException('No keyword found in kwlist.h');
        }
        $keywords = [];
        foreach ($matches as $match) {
            $keywords[strtoupper($match[1])] = $match[2];
        }
        ksort($keywords);

        return ['keywords' => $keywords];
    }
}
