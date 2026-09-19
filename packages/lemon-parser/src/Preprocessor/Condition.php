<?php

declare(strict_types=1);

namespace LemonParser\Preprocessor;

use LemonParser\Ast\Location;
use LemonParser\SyntaxException;

/**
 * Evaluates the expression after `%if`, `%ifdef` or `%ifndef`.
 *
 * Lemon reads the expression left to right with no precedence: a name is
 * true when it is defined, `!` negates the next term, parentheses group,
 * and `||` and `&&` stop as soon as the answer is known.
 *
 * @visibility root
 */
final class Condition
{
    /**
     * @param list<string> $defines The names defined on the command line
     */
    public function __construct(private readonly array $defines)
    {
    }

    /**
     * Evaluates an expression as `eval_preprocessor_boolean` does.
     *
     * @param string $text The expression
     * @param Location $location Where the directive is, for errors
     *
     * @return bool The value
     *
     * @throws SyntaxException When the expression is not well formed
     */
    public function evaluate(string $text, Location $location): bool
    {
        $result = false;
        $negate = false;
        $termExpected = true;
        $length = strlen($text);
        for ($index = 0; $index < $length; $index++) {
            $byte = $text[$index];
            if (ctype_space($byte)) {
                continue;
            }
            if ($byte === '!') {
                $this->guard($termExpected, $text, $index, $location);
                $negate = !$negate;
                continue;
            }
            if ($byte === '|' && ($text[$index + 1] ?? '') === '|') {
                $this->guard(!$termExpected, $text, $index, $location);
                if ($result) {
                    return true;
                }
                $index++;
                $termExpected = true;
                continue;
            }
            if ($byte === '&' && ($text[$index + 1] ?? '') === '&') {
                $this->guard(!$termExpected, $text, $index, $location);
                if (!$result) {
                    return false;
                }
                $index++;
                $termExpected = true;
                continue;
            }
            $this->guard($termExpected, $text, $index, $location);
            [$result, $index] = $this->term($text, $index, $location);
            $result = $negate ? !$result : $result;
            $negate = false;
            $termExpected = false;
        }

        return $result;
    }

    /**
     * Evaluates a parenthesised group or a name.
     *
     * @param string $text The whole expression
     * @param int $index Where the term begins
     * @param Location $location Where the directive is, for errors
     *
     * @return array{bool, int} The value and the index of the term's last byte
     *
     * @throws SyntaxException When the term is not a group or a name
     */
    public function term(string $text, int $index, Location $location): array
    {
        if ($text[$index] === '(') {
            $depth = 1;
            for ($end = $index + 1; $end < strlen($text); $end++) {
                if ($text[$end] === '(') {
                    $depth++;
                } elseif ($text[$end] === ')' && --$depth === 0) {
                    return [$this->evaluate(substr($text, $index + 1, $end - $index - 1), $location), $end];
                }
            }
            throw $this->error($text, strlen($text), $location);
        }
        if (preg_match('/\G[A-Za-z][A-Za-z0-9_]*/', $text, $match, 0, $index) !== 1) {
            throw $this->error($text, $index, $location);
        }

        return [in_array($match[0], $this->defines, true), $index + strlen($match[0]) - 1];
    }

    /**
     * Raises the error unless a condition holds.
     *
     * @param bool $condition What must hold
     * @param string $text The whole expression
     * @param int $index Where the problem is
     * @param Location $location Where the directive is
     *
     * @throws SyntaxException When the condition does not hold
     */
    public function guard(bool $condition, string $text, int $index, Location $location): void
    {
        if (!$condition) {
            throw $this->error($text, $index, $location);
        }
    }

    /**
     * Words the error as Lemon does.
     *
     * @param string $text The whole expression
     * @param int $index Where the problem is
     * @param Location $location Where the directive is
     *
     * @return SyntaxException The error
     */
    public function error(string $text, int $index, Location $location): SyntaxException
    {
        return new SyntaxException('%if syntax error: ' . substr($text, 0, $index + 1) . ' <-- syntax error here', $location);
    }
}
