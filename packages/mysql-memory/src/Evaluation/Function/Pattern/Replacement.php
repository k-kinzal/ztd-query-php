<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;

/**
 * The replacement text of REGEXP_REPLACE, read as ICU reads it: literal text and references to capture groups.
 *
 * `$n` is capture group n: the first digit is always part of it, a further digit only while the
 * number stays within the groups of the pattern; a first digit beyond them is an error
 * (ER_REGEXP_INDEX_OUTOFBOUNDS_ERROR). `${name}` is a named group. A `$` that is followed by
 * neither, or names no group, is an error (ER_REGEXP_INVALID_CAPTURE_GROUP_NAME). A backslash
 * makes the next character literal; a backslash at the end is dropped. A group that did not take
 * part in the match is empty (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/regexp.html#function_regexp-replace,
 * https://unicode-org.github.io/icu/userguide/strings/regexp.html#replacement-text.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Replacement
{
    /**
     * @param list<string|int> $parts Literal text, and the number of each group referred to
     */
    public function __construct(public readonly array $parts)
    {
    }

    /**
     * Reads a UTF-8 replacement text for an expression.
     *
     * @throws SqlError When a reference names no group of the expression
     */
    public static function of(string $text, Expression $expression): self
    {
        $characters = mb_str_split($text, 1, 'UTF-8');
        $parts = [];
        $literal = '';
        for ($index = 0, $count = count($characters); $index < $count; $index++) {
            $character = $characters[$index];
            if ($character === '\\') {
                $literal .= $characters[++$index] ?? '';
                continue;
            }
            if ($character !== '$') {
                $literal .= $character;
                continue;
            }
            $next = $characters[$index + 1] ?? '';
            if (ctype_digit($next)) {
                $group = (int) $next;
                $index++;
                if ($group > $expression->groups) {
                    throw DataError::RegexpIndexOutOfBounds->error();
                }
                while (ctype_digit($characters[$index + 1] ?? '') && $group * 10 + (int) $characters[$index + 1] <= $expression->groups) {
                    $group = $group * 10 + (int) $characters[++$index];
                }
            } elseif ($next === '{') {
                $close = array_search('}', array_slice($characters, $index + 2), true);
                $name = is_int($close) ? implode('', array_slice($characters, $index + 2, $close)) : '';
                if (!isset($expression->names[$name])) {
                    throw DataError::RegexpInvalidCaptureGroupName->error();
                }
                $group = $expression->names[$name];
                $index += 2 + (int) $close;
            } else {
                throw DataError::RegexpInvalidCaptureGroupName->error();
            }
            if ($literal !== '') {
                $parts[] = $literal;
                $literal = '';
            }
            $parts[] = $group;
        }
        if ($literal !== '') {
            $parts[] = $literal;
        }

        return new self($parts);
    }

    /**
     * Answers the text that replaces a match of a UTF-8 subject.
     *
     * @param list<array{int, int}|null> $spans The byte span of the match and of each group
     */
    public function expand(string $subject, array $spans): string
    {
        $text = '';
        foreach ($this->parts as $part) {
            if (is_string($part)) {
                $text .= $part;
                continue;
            }
            $span = $spans[$part] ?? null;
            $text .= $span === null ? '' : substr($subject, $span[0], $span[1] - $span[0]);
        }

        return $text;
    }
}
