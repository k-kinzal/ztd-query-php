<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\SqlError;

/**
 * A regular expression translated for PCRE: its source, and the capture groups it has.
 *
 * A search that PCRE gives up for its backtracking limit fails with ER_REGEXP_TIME_OUT, one that
 * runs out of stack with ER_REGEXP_STACK_OVERFLOW.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/regexp.html#regexp-compatibility.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Expression
{
    /**
     * @param string $source The PCRE pattern, with delimiters and modifiers
     * @param int $groups The number of capture groups
     * @param array<string, int> $names The number of each named group, by name
     * @param bool $located Whether it finds grapheme clusters or Unicode word boundaries, for which ICU reads the rules of a locale
     */
    public function __construct(public readonly string $source, public readonly int $groups, public readonly array $names = [], public readonly bool $located = false)
    {
    }

    /**
     * Finds the first match at or after a byte offset of a UTF-8 subject, or answers null.
     *
     * @return list<array{int, int}|null> The byte span of the match and of each group; null for a group that did not take part
     *
     * @throws SqlError When the search exceeds the limits of PCRE
     */
    public function find(string $subject, int $offset): ?array
    {
        set_error_handler(static fn (): bool => true);
        try {
            $matched = preg_match($this->source, $subject, $found, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL, $offset);
        } finally {
            restore_error_handler();
        }
        if ($matched === false) {
            throw match (preg_last_error()) {
                PREG_BACKTRACK_LIMIT_ERROR => DataError::RegexpTimeOut->error(),
                PREG_RECURSION_LIMIT_ERROR, PREG_JIT_STACKLIMIT_ERROR => DataError::RegexpStackOverflow->error(),
                default => DataError::RegexpError->error(),
            };
        }
        if ($matched === 0) {
            return null;
        }
        $spans = [];
        for ($group = 0; $group <= $this->groups; $group++) {
            [$text, $start] = $found[$group] ?? [null, -1];
            $spans[] = $text === null || $start < 0 ? null : [$start, $start + strlen($text)];
        }

        return $spans;
    }

    /**
     * Finds the matches of a UTF-8 subject from a byte offset, at most a number of them, as the server steps through them.
     *
     * After an empty match the next search starts one character further, so it is never the same
     * place twice (verified on a live 8.4 server).
     *
     * @param int $limit The most matches to find, or 0 for all
     * @param int $offset The byte offset the first search starts at; the text before it is still seen by lookbehind, and ^ does not match at it
     * @return list<list<array{int, int}|null>>
     *
     * @throws SqlError When a search exceeds the limits of PCRE
     */
    public function all(string $subject, int $limit, int $offset = 0): array
    {
        $matches = [];
        $length = strlen($subject);
        while ($offset <= $length) {
            $spans = $this->find($subject, $offset);
            if ($spans === null || $spans[0] === null) {
                break;
            }
            $matches[] = $spans;
            if (count($matches) === $limit) {
                break;
            }
            [$start, $end] = $spans[0];
            if ($end > $start) {
                $offset = $end;
            } elseif ($end >= $length) {
                break;
            } else {
                $offset = $end + max(1, strlen(mb_substr(substr($subject, $end, 4), 0, 1, 'UTF-8')));
            }
        }

        return $matches;
    }
}
