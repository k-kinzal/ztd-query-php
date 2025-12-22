<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Lemon;

use LogicException;
use RuntimeException;
use SqlFaker\Grammar\Grammar;
use SqlFaker\Grammar\NonTerminal;
use SqlFaker\Grammar\Production;
use SqlFaker\Grammar\ProductionRule;
use SqlFaker\Grammar\Symbol;
use SqlFaker\Grammar\Terminal;

/**
 * Parser for Lemon grammar files (e.g. SQLite's parse.y).
 *
 * Lemon grammar format:
 *   - Rules: lhs(alias) ::= symbol1 symbol2 ... . { action }
 *   - Each alternative is a separate rule with the same LHS
 *   - Directives: %token_type, %type, %left, %right, %nonassoc, %fallback, %wildcard, etc.
 *   - Tokens are ALL_CAPS, non-terminals are lowercase
 *   - C code blocks between %include { ... }
 */
final class LemonParser
{
    /** @var array<string, true> */
    private array $tokens = [];

    /** @var array<string, true> */
    private array $nonTerminals = [];

    /**
     * Parse a Lemon grammar string into a Grammar.
     */
    public function parse(string $input): Grammar
    {
        $this->tokens = [];
        $this->nonTerminals = [];

        $input = $this->stripComments($input);

        $this->extractTokens($input);

        $rules = $this->extractRules($input);

        if ($rules === []) {
            throw new LogicException('No grammar rules parsed from Lemon grammar.');
        }

        /** @var string $startSymbol */
        $startSymbol = array_key_first($rules);

        /** @var array<string, ProductionRule> $ruleMap */
        $ruleMap = [];

        foreach ($rules as $lhs => $alternatives) {
            /** @var list<Production> $productions */
            $productions = [];

            foreach ($alternatives as $symbolNames) {
                /** @var list<Symbol> $symbols */
                $symbols = [];

                foreach ($symbolNames as $name) {
                    if ($this->isTerminal($name)) {
                        $symbols[] = new Terminal($name);
                    } else {
                        $symbols[] = new NonTerminal($name);
                    }
                }

                $productions[] = new Production($symbols);
            }

            $ruleMap[$lhs] = new ProductionRule($lhs, $productions);
        }

        return new Grammar($startSymbol, $ruleMap);
    }

    /**
     * Parse a Lemon grammar file into a Grammar.
     */
    public function parseFile(string $path): Grammar
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Failed to read: {$path}");
        }
        return $this->parse($contents);
    }

    /**
     * Strip C-style comments from input.
     */
    private function stripComments(string $input): string
    {
        return preg_replace(['/\/\*.*?\*\//s', '/\/\/.*$/m'], '', $input) ?? $input;
    }

    /**
     * Extract token names from directives.
     */
    private function extractTokens(string $input): void
    {
        $patterns = [
            '/%token\s+(.+?)\.?\s*$/m',
            '/%(?:left|right|nonassoc)\s+(.+?)\.?\s*$/m',
            '/%fallback\s+(.+?)\.?\s*$/m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $input, $matches) > 0) {
                foreach ($matches[1] as $line) {
                    $this->registerTokenNames($line, '/\s+/');
                }
            }
        }

        if (preg_match_all('/%token_class\s+(\w+)\s+(.+?)\.?\s*$/m', $input, $matches) > 0) {
            $count = count($matches[0]);
            for ($i = 0; $i < $count; $i++) {
                $this->tokens[$matches[1][$i]] = true;
                $this->registerTokenNames($matches[2][$i], '/[\s|]+/');
            }
        }

        if (preg_match('/%wildcard\s+(\w+)/', $input, $match) === 1) {
            $this->tokens[$match[1]] = true;
        }
    }

    /**
     * Split a line by the given delimiter, trim/validate each token name, and register.
     */
    private function registerTokenNames(string $line, string $splitPattern): void
    {
        $names = preg_split($splitPattern, trim($line));
        if ($names === false) {
            return;
        }
        foreach ($names as $name) {
            $name = trim($name, '.');
            if ($name !== '' && self::isAllCaps($name)) {
                $this->tokens[$name] = true;
            }
        }
    }

    /**
     * Extract grammar rules from input.
     *
     * @return array<string, list<list<string>>>
     */
    private function extractRules(string $input): array
    {
        /** @var array<string, list<list<string>>> $rules */
        $rules = [];

        $input = $this->stripDirectiveBlocks($input);

        $pattern = '/^(\w+)(?:\([^)]*\))?\s*::=\s*(.*?)\.\s*(?:\{[^}]*\})?/ms';

        if (preg_match_all($pattern, $input, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $lhs = $match[1];
            $rhs = trim($match[2]);

            if (str_starts_with($lhs, '%')) {
                continue;
            }

            $this->nonTerminals[$lhs] = true;

            if (!isset($rules[$lhs])) {
                $rules[$lhs] = [];
            }
            $rules[$lhs][] = $rhs !== '' ? $this->parseRhsSymbols($rhs) : [];
        }

        return $rules;
    }

    /**
     * Strip %include, %destructor, %type and other directive blocks.
     */
    private function stripDirectiveBlocks(string $input): string
    {
        return preg_replace([
            '/%(?:include|destructor|syntax_error|parse_accept|parse_failure|stack_overflow|code)\s*\{[^}]*\}/s',
            '/^%(?:token_type|default_type|extra_context|name|token_prefix|stack_size|ifdef|ifndef|endif)\b.*$/m',
        ], '', $input) ?? $input;
    }

    /**
     * Parse RHS string into symbol names, stripping aliases.
     *
     * @return list<string>
     */
    private function parseRhsSymbols(string $rhs): array
    {
        /** @var list<string> $symbols */
        $symbols = [];

        $parts = preg_split('/\s+/', $rhs);
        if ($parts === false) {
            return [];
        }

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            if (str_contains($part, '|')) {
                $options = explode('|', $part);
                $first = $this->stripAlias($options[0]);
                if ($first !== '' && !str_starts_with($first, '%')) {
                    $symbols[] = $first;
                    foreach ($options as $option) {
                        $name = $this->stripAlias($option);
                        if ($name !== '' && self::isAllCaps($name)) {
                            $this->tokens[$name] = true;
                        }
                    }
                }
                continue;
            }

            $name = $this->stripAlias($part);

            if ($name === '' || str_starts_with($name, '%')) {
                continue;
            }

            if (self::isAllCaps($name)) {
                $this->tokens[$name] = true;
            } else {
                $this->nonTerminals[$name] = true;
            }

            $symbols[] = $name;
        }

        return $symbols;
    }

    /**
     * Strip Lemon alias from a symbol: "expr(A)" -> "expr"
     */
    private function stripAlias(string $symbol): string
    {
        $pos = strpos($symbol, '(');
        if ($pos !== false) {
            return substr($symbol, 0, $pos);
        }
        return $symbol;
    }

    /**
     * Determine if a symbol name is a terminal.
     * In Lemon: ALL_CAPS = terminal, lowercase = non-terminal.
     */
    private function isTerminal(string $name): bool
    {
        if (isset($this->tokens[$name])) {
            return true;
        }

        if (isset($this->nonTerminals[$name])) {
            return false;
        }

        return self::isAllCaps($name);
    }

    /**
     * Check if a name matches the Lemon ALL_CAPS token convention.
     */
    private static function isAllCaps(string $name): bool
    {
        return preg_match('/^[A-Z][A-Z0-9_]*$/', $name) === 1;
    }
}
