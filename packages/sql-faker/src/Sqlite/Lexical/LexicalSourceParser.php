<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Lexical;

use RuntimeException;

/**
 * Parses SQLite's official tokenize.c lexical dispatch inventory.
 */
final class LexicalSourceParser
{
    /**
     * @return list<string>
     *
     * @throws RuntimeException When the upstream tokenizer declares no character classes, or leaves one unclassified
     */
    public function parseCharacterClasses(string $source): array
    {
        preg_match_all('/^#define\s+(CC_[A-Z0-9_]+)\s+\d+\b/m', $source, $definitionMatches);
        $defined = array_values(array_unique($definitionMatches[1]));
        if ($defined === []) {
            throw new RuntimeException('SQLite lexer character-class definitions were not found.');
        }

        $functionOffset = strpos($source, 'int sqlite3GetToken(');
        if ($functionOffset === false) {
            throw new RuntimeException('SQLite sqlite3GetToken() was not found.');
        }
        $function = substr($source, $functionOffset);
        $switchOffset = strpos($function, 'switch(');
        if ($switchOffset === false
            || preg_match('/^}\s*$/m', $function, $endMatch, PREG_OFFSET_CAPTURE, $switchOffset) !== 1
        ) {
            throw new RuntimeException('SQLite sqlite3GetToken() body was not terminated.');
        }
        $function = substr($function, 0, $endMatch[0][1] + strlen($endMatch[0][0]));
        preg_match_all('/\bcase\s+(CC_[A-Z0-9_]+)\s*:/', $function, $caseMatches);
        $cases = $caseMatches[1];

        $missing = array_diff($defined, $cases, ['CC_ILLEGAL']);
        if ($missing !== []) {
            throw new RuntimeException('SQLite tokenizer does not classify character classes: ' . implode(', ', $missing));
        }
        if (!str_contains($function, 'default:') || !str_contains($function, '*tokenType = TK_ILLEGAL;')) {
            throw new RuntimeException('SQLite tokenizer default illegal-token branch was not found.');
        }

        return $defined;
    }
}
