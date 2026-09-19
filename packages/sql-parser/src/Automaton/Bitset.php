<?php

declare(strict_types=1);

namespace SqlParser\Automaton;

/**
 * Sets of terminals as arrays of 32-bit words.
 *
 * Lookahead computation unions tens of thousands of sets, so they are kept
 * as plain word arrays and operated on in place rather than as objects.
 *
 * @visibility root
 */
final class Bitset
{
    /**
     * Makes an empty set able to hold a number of members.
     *
     * @param int $size How many members the set may hold
     *
     * @return array<int, int> The words, all zero
     */
    public static function empty(int $size): array
    {
        return array_fill(0, intdiv($size + 31, 32), 0);
    }

    /**
     * Adds a member.
     *
     * @param array<int, int> $words Set to add to
     * @param int $member Member to add
     */
    public static function add(array &$words, int $member): void
    {
        $words[$member >> 5] |= 1 << ($member & 31);
    }

    /**
     * Reports whether a member is present.
     *
     * @param array<int, int> $words Set to look in
     * @param int $member Member to look for
     *
     * @return bool True when present
     */
    public static function has(array $words, int $member): bool
    {
        return ($words[$member >> 5] & (1 << ($member & 31))) !== 0;
    }

    /**
     * Adds every member of one set to another.
     *
     * @param array<int, int> $into Set to add to
     * @param array<int, int> $from Set whose members are added
     */
    public static function union(array &$into, array $from): void
    {
        foreach ($from as $index => $word) {
            $into[$index] |= $word;
        }
    }

    /**
     * Lists the members in ascending order.
     *
     * @param array<int, int> $words Set to list
     *
     * @return list<int> The members
     */
    public static function members(array $words): array
    {
        $members = [];
        foreach ($words as $index => $word) {
            for ($bit = 0; $word !== 0; $bit++) {
                if (($word & 1) === 1) {
                    $members[] = $index * 32 + $bit;
                }
                $word >>= 1;
            }
        }

        return $members;
    }
}
