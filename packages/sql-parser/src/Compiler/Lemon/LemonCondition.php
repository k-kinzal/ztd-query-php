<?php

declare(strict_types=1);

namespace SqlParser\Compiler\Lemon;

use SqlParser\Compiler\GrammarSourceException;

/**
 * Evaluates the condition of a Lemon `%if` against the names defined for a build.
 *
 * Lemon's evaluator reads `&&` and `||` from left to right without giving
 * either priority, negates with `!`, and groups with parentheses; a bare name
 * is true when it is defined.
 *
 * @visibility root
 */
final class LemonCondition
{
    /**
     * @param list<string> $defines Names defined for the build, as `-D` would pass them
     */
    public function __construct(private readonly array $defines = [])
    {
    }

    /**
     * Evaluates a condition.
     *
     * @param string $condition Text after the directive
     *
     * @return bool Its value
     *
     * @throws GrammarSourceException When the condition is malformed
     */
    public function evaluate(string $condition): bool
    {
        $tokens = preg_split('/\s*(&&|\|\||[!()])\s*|\s+/', trim($condition), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        if ($tokens === false || $tokens === []) {
            throw GrammarSourceException::unexpected('a condition', 'nothing', 0);
        }
        $position = 0;
        $value = $this->expression($tokens, $position);
        if ($position !== count($tokens)) {
            throw GrammarSourceException::unexpected('the end of the condition', "'{$tokens[$position]}'", 0);
        }

        return $value;
    }

    /**
     * Evaluates terms joined by `&&` and `||`, from left to right.
     *
     * @param list<string> $tokens Condition tokens
     * @param int $position Token to read next, advanced past what was read
     *
     * @return bool The value
     *
     * @throws GrammarSourceException When an operator has no right-hand term
     */
    public function expression(array $tokens, int &$position): bool
    {
        $value = $this->term($tokens, $position);
        while (($operator = $tokens[$position] ?? null) === '&&' || $operator === '||') {
            $position++;
            $right = $this->term($tokens, $position);
            $value = $operator === '&&' ? ($value && $right) : ($value || $right);
        }

        return $value;
    }

    /**
     * Evaluates a name, a negation or a parenthesised condition.
     *
     * @param list<string> $tokens Condition tokens
     * @param int $position Token to read next, advanced past what was read
     *
     * @return bool The value
     *
     * @throws GrammarSourceException When a term is missing or a parenthesis is not closed
     */
    public function term(array $tokens, int &$position): bool
    {
        $token = $tokens[$position++] ?? null;
        if ($token === '!') {
            return !$this->term($tokens, $position);
        }
        if ($token === '(') {
            $value = $this->expression($tokens, $position);
            if (($tokens[$position++] ?? null) !== ')') {
                throw GrammarSourceException::unexpected("')'", 'the end of the condition', 0);
            }

            return $value;
        }
        if ($token === null || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $token) !== 1) {
            throw GrammarSourceException::unexpected('a name', $token === null ? 'nothing' : "'{$token}'", 0);
        }

        return in_array($token, $this->defines, true);
    }
}
