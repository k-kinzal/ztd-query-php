<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use RuntimeException;

/**
 * Extracts MySQL's keyword and function-token tables from sql/lex.h.
 *
 * @visibility root
 */
final class LexicalProfileCompiler
{
    /**
     * Preserves the registration class declared in lex.h, including hint-only entries.
     * Legacy separate tables have the same keyword/function distinction.
     * @return array<string, array<string, list<string>>>
     * @throws RuntimeException When neither supported table syntax is present
     */
    public function registrations(string $source): array
    {
        $modern = $this->extractModern($source, 'SYM_FN');
        if ($modern !== []) {
            $classes = [];
            foreach (['SYM', 'SYM_FN', 'SYM_HK', 'SYM_H'] as $class) {
                preg_match_all(
                    '/\{\s*' . $class . '\(\s*"((?:\\\\.|[^"\\\\])*)"\s*,\s*([A-Z][A-Z0-9_]*)\s*\)\s*\}/',
                    $source,
                    $matches,
                    PREG_SET_ORDER,
                );
                $classes[$class] = $this->group($matches);
            }
            return $classes;
        }
        $legacy = $this->compile($source);
        return ['SYM' => $legacy['symbols'], 'SYM_FN' => $legacy['functions'], 'SYM_HK' => [], 'SYM_H' => []];
    }

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
     * Reads the keyword table as releases from 8.0 onward declare it.
     *
     * Those releases wrap each entry in a SYM macro, and the hash-key and
     * hidden variants of that macro declare the same kind of entry.
     *
     * @param string $source Contents of the upstream lexer header
     * @param string $macro Macro the wanted entries are wrapped in
     *
     * @return array<string, list<string>> Token name => the lexemes that produce it
     */
    public function extractModern(string $source, string $macro): array
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
     * Reads the keyword table as releases before 8.0 declare it.
     *
     * Those releases write the lexeme first and wrap only the token in SYM,
     * and they keep keywords and functions in two tables rather than
     * distinguishing them by macro.
     *
     * @param string $source Contents of one table from the upstream lexer header
     *
     * @return array<string, list<string>> Token name => the lexemes that produce it
     */
    public function extractLegacy(string $source): array
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
     * Files each lexeme under the token it produces, without repeating one.
     *
     * @param list<array<string>> $matches Lexeme and token pairs as they were read
     *
     * @return array<string, list<string>> Token name => the lexemes that produce it
     */
    public function group(array $matches): array
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
