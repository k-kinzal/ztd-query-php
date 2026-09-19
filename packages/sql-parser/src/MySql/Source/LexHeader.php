<?php

declare(strict_types=1);

namespace SqlParser\MySql\Source;

use RuntimeException;

/**
 * Extracts the keyword tables from MySQL's `sql/lex.h`.
 *
 * Releases before 8.0 keep two arrays, `symbols[]` for keywords and
 * `sql_functions[]` for function names. From 8.0 on a single array marks each
 * entry with its group: `SYM` and `SYM_HK` are keywords, `SYM_FN` are
 * function names, and `SYM_H` are optimizer hints, which the SQL lexer never
 * produces.
 *
 * @visibility root
 */
final class LexHeader
{
    /**
     * Reads the keyword tables.
     *
     * @param string $source Contents of `lex.h`
     *
     * @return array{keywords: array<string, string>, functions: array<string, string>} Terminal name by upper-cased spelling
     *
     * @throws RuntimeException When the file declares no keyword
     */
    public function parse(string $source): array
    {
        $functionsAt = strpos($source, 'sql_functions[]');
        $keywords = [];
        $functions = [];
        foreach ($this->entries($source) as [$macro, $spelling, $terminal, $offset]) {
            if ($macro === 'SYM_H') {
                continue;
            }
            if ($macro === 'SYM_FN' || ($macro === 'SYM' && $functionsAt !== false && $offset > $functionsAt)) {
                $functions[$spelling] = $terminal;
            } else {
                $keywords[$spelling] = $terminal;
            }
        }
        if ($keywords === []) {
            throw new RuntimeException('No keyword found in lex.h');
        }
        ksort($keywords);
        ksort($functions);

        return ['keywords' => $keywords, 'functions' => $functions];
    }

    /**
     * Reads every keyword entry, in either release's spelling.
     *
     * An entry may span two lines, so the whole file is searched and each
     * entry is reported with the offset it was found at.
     *
     * @param string $source Contents of `lex.h`
     *
     * @return list<array{string, string, string, int}> Macro, upper-cased spelling, terminal and offset of each entry
     */
    public function entries(string $source): array
    {
        $entries = [];
        preg_match_all('/\{\s*(SYM(?:_FN|_HK|_H)?)\s*\(\s*"([^"]+)"\s*,\s*([A-Za-z0-9_]+)\s*\)\s*\}/', $source, $modern, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($modern as $match) {
            $entries[] = [$match[1][0], strtoupper($match[2][0]), $match[3][0], $match[0][1]];
        }
        preg_match_all('/\{\s*"([^"]+)"\s*,\s*SYM\s*\(\s*([A-Za-z0-9_]+)\s*\)\s*\}/', $source, $legacy, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($legacy as $match) {
            $entries[] = ['SYM', strtoupper($match[1][0]), $match[2][0], $match[0][1]];
        }

        return $entries;
    }
}
