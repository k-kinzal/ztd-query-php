<?php

declare(strict_types=1);

namespace SqlFaker\Sqlite\Generation\Lexeme;

use SqlFaker\Grammar\LexicalException;

/**
 * The word flags and validity conditions in SQLite 3.47.2 select.c/sqlite3JoinType.
 * @see https://github.com/sqlite/sqlite/blob/version-3.47.2/src/select.c
 */
final class JoinModifiers
{
    private const WORDS = [
        'NATURAL' => 1,
        'LEFT' => 2 | 4,
        'OUTER' => 4,
        'RIGHT' => 8 | 4,
        'FULL' => 2 | 8 | 4,
        'INNER' => 16,
        'CROSS' => 16 | 32,
    ];

    /**
     * Reads the semantic flag of a word recognized by sqlite3JoinType.
     * @throws LexicalException When registration data contains an unimplemented join word
     */
    public function mask(string $word): int
    {
        return self::WORDS[strtoupper($word)] ?? throw new LexicalException('Unknown SQLite join modifier: ' . $word);
    }

    /**
     * Rejects incompatible inner/outer flags, bare OUTER and NATURAL joins carrying ON or USING.
     */
    public function valid(int $mask, bool $hasCondition): bool
    {
        return ($mask & (16 | 4)) !== (16 | 4)
            && ($mask & (4 | 2 | 8)) !== 4
            && (!$hasCondition || ($mask & 1) === 0);
    }

    /**
     * Checks every reachable flag state of the still-unselected prefix, independently of spelling choices.
     * @param list<string> $words Spellings available for the fixed release
     * @throws LexicalException When registration data contains an unimplemented join word
     */
    public function canComplete(int $mask, int $remaining, bool $hasCondition, array $words): bool
    {
        $states = [$mask => true];
        for ($position = 0; $position < $remaining; ++$position) {
            $next = [];
            foreach (array_keys($states) as $state) {
                foreach ($words as $word) {
                    $next[$state | $this->mask($word)] = true;
                }
            }
            $states = $next;
        }
        foreach (array_keys($states) as $state) {
            if ($this->valid($state, $hasCondition)) {
                return true;
            }
        }
        return false;
    }
}
