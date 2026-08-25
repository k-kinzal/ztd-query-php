<?php

declare(strict_types=1);

namespace SqlFaker\MySql;

use RuntimeException;

/**
 * Extracts the complete MySQL lexer-state inventory from official sources.
 */
final class LexicalSourceParser
{
    /**
     * @return list<string>
     *
     * @throws RuntimeException When the upstream source declares no lexical state enum, or the scanner leaves a state unclassified
     */
    public function parseStates(string $stateHeader, string $scanner): array
    {
        if (preg_match('/enum\b[^\{]*\bmy_lex_states\s*\{(?<body>.*?)\}/s', $stateHeader, $match) !== 1) {
            throw new RuntimeException('MySQL lexical state enum was not found.');
        }
        preg_match_all('/\bMY_LEX_[A-Z0-9_]+\b/', $match['body'], $matches);
        $states = array_values(array_unique($matches[0]));
        if ($states === []) {
            throw new RuntimeException('MySQL lexical state inventory was empty.');
        }

        foreach ($states as $state) {
            if (!str_contains($scanner, 'case ' . $state . ':')
                && !in_array($state, ['MY_LEX_ESCAPE'], true)
            ) {
                throw new RuntimeException("MySQL scanner does not classify lexical state: {$state}");
            }
        }

        return $states;
    }
}
