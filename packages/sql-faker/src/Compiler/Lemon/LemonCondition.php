<?php

declare(strict_types=1);

namespace SqlFaker\Compiler\Lemon;

use RuntimeException;

/**
 * Evaluates the named boolean conditions used by Lemon's preprocess_input.
 * tool/lemon.c short-circuits a sequence from left to right; parentheses provide grouping.
 */
final class LemonCondition
{
    /**
     * @param list<string> $defines Names passed as Lemon -D definitions
     */
    public function __construct(private readonly array $defines = [])
    {
    }

    /**
     * Evaluates a condition without executing host-language code or consulting process environment.
     * @throws RuntimeException When a condition is malformed
     */
    public function evaluate(string $condition): bool
    {
        $tokens = preg_split('/\s*(&&|\|\||[!()])\s*|\s+/', trim($condition), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || $tokens === []) {
            throw new RuntimeException('Empty Lemon preprocessor condition.');
        }
        $offset = 0;
        $result = $this->expression($tokens, $offset);
        if ($offset !== count($tokens)) {
            throw new RuntimeException('Unexpected Lemon condition token: ' . $tokens[$offset]);
        }
        return $result;
    }

    /**
     * Mirrors Lemon's short-circuit grouping for mixed operators, without C precedence assumptions.
     * @param list<string> $tokens
     * @throws RuntimeException When a condition is malformed
     */
    public function expression(array $tokens, int &$offset): bool
    {
        $left = $this->term($tokens, $offset);
        $operator = $tokens[$offset] ?? null;
        if ($operator === null || $operator === ')') {
            return $left;
        }
        if ($operator !== '&&' && $operator !== '||') {
            throw new RuntimeException('Invalid Lemon condition operator: ' . $operator);
        }
        ++$offset;
        $right = $this->expression($tokens, $offset);
        return $operator === '&&' ? $left && $right : $left || $right;
    }

    /**
     * Reads a name, negation or parenthesized condition.
     * @param list<string> $tokens
     * @throws RuntimeException When a term or its closing parenthesis is missing
     */
    public function term(array $tokens, int &$offset): bool
    {
        $token = $tokens[$offset++] ?? '';
        if ($token === '!') {
            return !$this->term($tokens, $offset);
        }
        if ($token === '(') {
            $result = $this->expression($tokens, $offset);
            if (($tokens[$offset++] ?? null) !== ')') {
                throw new RuntimeException('Unclosed Lemon preprocessor condition.');
            }
            return $result;
        }
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/D', $token) !== 1) {
            throw new RuntimeException('Invalid Lemon preprocessor term: ' . $token);
        }
        return in_array($token, $this->defines, true);
    }
}
