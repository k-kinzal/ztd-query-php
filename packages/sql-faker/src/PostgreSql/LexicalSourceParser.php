<?php

declare(strict_types=1);

namespace SqlFaker\PostgreSql;

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

        preg_match_all('/^%x[ \t]+(.+)$/m', $sections[0], $stateMatches);
        $states = [];
        foreach ($stateMatches[1] as $declaration) {
            $declaredStates = preg_split('/\s+/', $declaration);
            foreach ($declaredStates === false ? [] : $declaredStates as $state) {
                if ($state !== '') {
                    $states[] = $state;
                }
            }
        }
        $states = array_values(array_unique($states));
        if ($states === []) {
            throw new RuntimeException('PostgreSQL Flex start conditions were not found.');
        }

        $rules = [];
        $ruleLines = preg_split('/\R/', $sections[1]);
        foreach ($ruleLines === false ? [] : $ruleLines as $line) {
            if ($line === '' || preg_match('/^\s/', $line) === 1
                || str_starts_with($line, '/*') || str_starts_with($line, '}')
                || preg_match('/^<[^>]+>\{$/', $line) === 1
            ) {
                continue;
            }
            if (preg_match('/^(?<pattern>\S+)\s+(?:\{|\|)/', $line, $ruleMatch) !== 1) {
                throw new RuntimeException("Unsupported PostgreSQL Flex rule declaration: {$line}");
            }
            if (!str_contains($ruleMatch['pattern'], '<<EOF>>')) {
                $rules[] = $ruleMatch['pattern'];
            }
        }
        if ($rules === []) {
            throw new RuntimeException('PostgreSQL Flex rule inventory was empty.');
        }

        preg_match_all('/\bcur_token\s*=\s*([A-Z][A-Z0-9_]*_LA)\s*;/', $parser, $lookaheadMatches);
        $lookaheadTokens = array_values(array_unique($lookaheadMatches[1]));
        sort($lookaheadTokens);
        if ($lookaheadTokens === []) {
            throw new RuntimeException('PostgreSQL parser lookahead tokens were not found.');
        }

        return [
            'states' => $states,
            'rules' => $rules,
            'lookahead_tokens' => $lookaheadTokens,
        ];
    }
}
