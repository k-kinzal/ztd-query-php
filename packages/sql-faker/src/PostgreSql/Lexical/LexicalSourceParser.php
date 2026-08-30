<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql\Lexical;

use RuntimeException;

/**
 * Parses PostgreSQL's official scan.l and parser.c lexical definitions.
 */
final class LexicalSourceParser
{
    /**
     * @return array{states: list<string>, rules: list<string>, lookahead_tokens: list<string>}
     *
     * @throws RuntimeException When the upstream Flex source is not shaped as a scanner, or declares no rules or lookahead tokens
     */
    public function parse(string $scanner, string $parser): array
    {
        $sections = preg_split('/^%%\s*$/m', $scanner);
        if ($sections === false || count($sections) !== 3) {
            throw new RuntimeException('PostgreSQL Flex source must contain exactly two section delimiters.');
        }

        return [
            'states' => $this->states($sections[0]),
            'rules' => $this->rules($sections[1]),
            'lookahead_tokens' => $this->lookaheadTokens($parser),
        ];
    }

    /**
     * Reads the start conditions the scanner declares.
     *
     * Flex allows several to be declared on one line, so a declaration is read
     * as a run of names rather than as a single one.
     *
     * @param string $definitions The scanner's definition section
     *
     * @return list<string> Every start condition, once each
     *
     * @throws RuntimeException When the section declares none
     */
    public function states(string $definitions): array
    {
        preg_match_all('/^%x[ \t]+(.+)$/m', $definitions, $matches);
        $states = [];
        foreach ($matches[1] as $declaration) {
            $declared = preg_split('/\s+/', $declaration);
            foreach ($declared === false ? [] : $declared as $state) {
                if ($state !== '') {
                    $states[] = $state;
                }
            }
        }
        $states = array_values(array_unique($states));
        if ($states === []) {
            throw new RuntimeException('PostgreSQL Flex start conditions were not found.');
        }

        return $states;
    }

    /**
     * Reads the patterns the scanner's rule section is written as.
     *
     * A rule begins in the first column; anything indented is the action of the
     * rule above it, and a comment or a brace is not a rule at all. The
     * end-of-file rule matches no input, so it is not part of the inventory.
     *
     * @param string $ruleSection The scanner's rule section
     *
     * @return list<string> Every rule pattern, in the order they are written
     *
     * @throws RuntimeException When a line is not a rule this reader knows, or the section holds none
     */
    public function rules(string $ruleSection): array
    {
        $rules = [];
        $lines = preg_split('/\R/', $ruleSection);
        foreach ($lines === false ? [] : $lines as $line) {
            if ($line === '' || preg_match('/^\s/', $line) === 1
                || str_starts_with($line, '/*') || str_starts_with($line, '}')
                || preg_match('/^<[^>]+>\{$/', $line) === 1
            ) {
                continue;
            }
            if (preg_match('/^(?<pattern>\S+)\s+(?:\{|\|)/', $line, $match) !== 1) {
                throw new RuntimeException("Unsupported PostgreSQL Flex rule declaration: {$line}");
            }
            if (!str_contains($match['pattern'], '<<EOF>>')) {
                $rules[] = $match['pattern'];
            }
        }
        if ($rules === []) {
            throw new RuntimeException('PostgreSQL Flex rule inventory was empty.');
        }

        return $rules;
    }

    /**
     * Reads the tokens the parser frontend rewrites by looking ahead.
     *
     * @param string $parser The parser frontend's source
     *
     * @return list<string> Every lookahead token, once each, in name order
     *
     * @throws RuntimeException When the frontend names none
     */
    public function lookaheadTokens(string $parser): array
    {
        preg_match_all('/\bcur_token\s*=\s*([A-Z][A-Z0-9_]*_LA)\s*;/', $parser, $matches);
        $tokens = array_values(array_unique($matches[1]));
        sort($tokens);
        if ($tokens === []) {
            throw new RuntimeException('PostgreSQL parser lookahead tokens were not found.');
        }

        return $tokens;
    }
}
