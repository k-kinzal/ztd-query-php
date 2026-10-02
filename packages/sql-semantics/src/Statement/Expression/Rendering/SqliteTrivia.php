<?php

declare(strict_types=1);

namespace SqlSemantics\Statement\Expression\Rendering;

/**
 * Recognizes bounded SQLite whitespace/comments between two semantic symbols.
 * This linear scanner neither interprets an expression nor supplies a raw fallback.
 * @visibility SqlSemantics
 */
final class SqliteTrivia
{
    /**
     * A line comment must terminate before the following semantic symbol.
     */
    public function end(string $text, int $start = 0): int
    {
        $length = strlen($text);
        $offset = $start;
        while ($offset < $length) {
            if (str_contains(" \t\r\n\f\v", $text[$offset])) {
                ++$offset;
                continue;
            }
            $prefix = substr($text, $offset, 2);
            if ($prefix === '/*') {
                $end = strpos($text, '*/', $offset + 2);
                if ($end === false) {
                    return $offset;
                }
                $offset = $end + 2;
                continue;
            }
            if ($prefix === '--') {
                $end = $offset + 2 + strcspn($text, "\r\n", $offset + 2);
                if ($end >= $length) {
                    return $offset;
                }
                $offset = $end + 1;
                continue;
            }
            break;
        }
        return $offset;
    }

    /**
     * Requires the whole gap to consist of complete trivia.
     */
    public function accepts(string $text): bool
    {
        return $this->end($text) === strlen($text);
    }

    /**
     * Checks the exact operator words with trivia only between their symbols.
     */
    public function operator(string $spelling, \SqlSemantics\Statement\Expression\SqliteBinaryOperator $operator): bool
    {
        $offset = 0;
        foreach (explode(' ', $operator->value) as $position => $word) {
            $next = $this->end($spelling, $offset);
            if ($position > 0 && $next === $offset) {
                return false;
            }
            if (\SqlSemantics\Statement\Identifier\Ascii::upper(substr($spelling, $next, strlen($word))) !== $word) {
                return false;
            }
            $offset = $next + strlen($word);
        }
        return $this->end($spelling, $offset) === strlen($spelling);
    }
}
