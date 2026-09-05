<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Lemon;

/**
 * Reads the tokens a Lemon grammar declares before any rule is written.
 *
 * Lemon declares its tokens in several places: outright with %token, in
 * passing while giving them a precedence, as a fallback, as a named class of
 * tokens that stand for one another, and as the single wildcard token. Each
 * form says the same thing — this name is a token — so all of them are read
 * together and told to the symbol table.
 *
 * @visibility root
 */
final class LemonDirectives
{
    /**
     * Tells the symbol table about every token the directives name.
     *
     * @param string $input Contents of the grammar file
     * @param LemonSymbols $symbols Symbol table to record them in
     */
    public function declareInto(string $input, LemonSymbols $symbols): void
    {
        $patterns = [
            '/%token\s+(.+?)\.?\s*$/m',
            '/%(?:left|right|nonassoc)\s+(.+?)\.?\s*$/m',
            '/%fallback\s+(.+?)\.?\s*$/m',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $input, $matches) > 0) {
                foreach ($matches[1] as $line) {
                    $symbols->declareTokensOn($line, '/\s+/');
                }
            }
        }

        if (preg_match_all('/%token_class\s+(\w+)\s+(.+?)\.?\s*$/m', $input, $matches) > 0) {
            $count = count($matches[0]);
            for ($index = 0; $index < $count; $index++) {
                $symbols->declareToken($matches[1][$index]);
                $symbols->declareTokensOn($matches[2][$index], '/[\s|]+/');
            }
        }

        if (preg_match('/%wildcard\s+(\w+)/', $input, $match) === 1) {
            $symbols->declareToken($match[1]);
        }
    }
}
