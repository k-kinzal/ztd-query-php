<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use RuntimeException;

/**
 * Extracts MySQL's keyword and function-token tables from sql/lex.h.
 */
final class LexicalProfileCompiler
{
    /**
     * @return array{symbols: array<string, list<string>>, functions: array<string, list<string>>}
     *
     * @throws RuntimeException When the upstream source declares no keyword or function table
     */
    public function compile(string $source): array
    {
        $symbols = $this->extractModern($source, 'SYM');
        $functions = $this->extractModern($source, 'SYM_FN');

        if ($functions === []) {
            $functionOffset = strpos($source, 'sql_functions');
            if ($functionOffset === false) {
                throw new RuntimeException('MySQL sql_functions table was not found.');
            }

            $symbols = $this->extractLegacy(substr($source, 0, $functionOffset));
            $functions = $this->extractLegacy(substr($source, $functionOffset));
        }

        if ($symbols === [] || $functions === []) {
            throw new RuntimeException('MySQL lexical tables were empty.');
        }

        return ['symbols' => $symbols, 'functions' => $functions];
    }

    /**
     * @return array<string, list<string>>
     */
    private function extractModern(string $source, string $macro): array
    {
        $macroPattern = $macro === 'SYM' ? 'SYM(?:_HK|_H)?' : $macro;
        preg_match_all(
            '/\{\s*' . $macroPattern . '\(\s*"((?:\\\\.|[^"\\\\])*)"\s*,\s*([A-Z][A-Z0-9_]*)\s*\)\s*\}/',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        return $this->group($matches);
    }

    /**
     * @return array<string, list<string>>
     */
    private function extractLegacy(string $source): array
    {
        preg_match_all(
            '/\{\s*"((?:\\\\.|[^"\\\\])*)"\s*,\s*SYM\(\s*([A-Z][A-Z0-9_]*)\s*\)\s*\}/',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        return $this->group($matches);
    }

    /**
     * @param list<array<string>> $matches
     * @return array<string, list<string>>
     */
    private function group(array $matches): array
    {
        /** @var array<string, list<string>> $tokens */
        $tokens = [];
        foreach ($matches as $match) {
            $lexeme = stripcslashes($match[1]);
            $token = $match[2];
            if (!in_array($lexeme, $tokens[$token] ?? [], true)) {
                $tokens[$token][] = $lexeme;
            }
        }

        ksort($tokens);

        return $tokens;
    }
}
