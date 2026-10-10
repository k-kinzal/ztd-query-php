<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Special;

/**
 * A set of global transaction identifiers: per source UUID and tag, the transaction numbers as closed intervals.
 *
 * A set is written as source UUIDs separated by commas, each followed by colon-separated
 * intervals `n` or `n-m`; MySQL 8.3 and later also take tags, which apply to the intervals after
 * them. Blanks may surround every part, empty elements between commas are skipped, an interval
 * whose end is below its start is empty, a missing end reads as 0, and a number is at most
 * 9223372036854775806. The text of a set lists the UUIDs in order, in lower case, separated by a
 * comma and a newline, the untagged intervals first and then each tag in order (verified on live
 * 5.7.44, 8.0.44, 8.4 and 9.1.0 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/replication-gtids-concepts.html.
 *
 * @visibility MySqlMemory
 */
final class GtidSet
{
    /**
     * The largest transaction number.
     */
    public const LARGEST = 9223372036854775806;

    /**
     * @param array<string, array<string, list<array{int, int}>>> $intervals The intervals by lower-case UUID and tag ('' untagged), each list sorted and merged
     */
    public function __construct(public readonly array $intervals = [])
    {
    }

    /**
     * Reads the text of a set, or answers null when it is malformed.
     *
     * @param bool $tags Whether the release takes tags
     */
    public static function parse(string $text, bool $tags): ?self
    {
        $intervals = [];
        $length = strlen($text);
        $at = self::blank($text, 0);
        while ($at < $length) {
            if ($text[$at] === ',') {
                $at = self::blank($text, $at + 1);
                continue;
            }
            if (preg_match('/\G[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}/', $text, $match, 0, $at) !== 1) {
                return null;
            }
            $uuid = strtolower($match[0]);
            $tag = '';
            $at = self::blank($text, $at + 36);
            while ($at < $length && $text[$at] === ':') {
                $at = self::blank($text, $at + 1);
                if ($tags && preg_match('/\G[A-Za-z_][A-Za-z0-9_]*/', $text, $word, 0, $at) === 1) {
                    if (strlen($word[0]) > 32) {
                        return null;
                    }
                    $tag = strtolower($word[0]);
                    $at = self::blank($text, $at + strlen($word[0]));
                    continue;
                }
                $interval = self::interval($text, $at);
                if ($interval === null) {
                    return null;
                }
                [$first, $last, $at] = $interval;
                if ($last >= $first) {
                    $intervals[$uuid][$tag][] = [$first, $last];
                }
            }
            if ($at < $length && $text[$at] !== ',') {
                return null;
            }
        }

        return (new self($intervals))->normalized();
    }

    /**
     * Reads an interval `n` or `n-m` at a position: its first and last numbers and the position after it and its blanks, or null when it is malformed.
     *
     * @return array{int, int, int}|null
     */
    public static function interval(string $text, int $at): ?array
    {
        if (preg_match('/\G\+?([0-9]+)/', $text, $start, 0, $at) !== 1) {
            return null;
        }
        $first = self::number($start[1]);
        if ($first === null || $first === 0) {
            return null;
        }
        $last = $first;
        $at = self::blank($text, $at + strlen($start[0]));
        if ($at < strlen($text) && $text[$at] === '-') {
            $at = self::blank($text, $at + 1);
            preg_match('/\G\+?([0-9]*)/', $text, $end, 0, $at);
            $last = self::number($end[1] ?? '');
            if ($last === null) {
                return null;
            }
            $at = self::blank($text, $at + strlen($end[0] ?? ''));
        }

        return [$first, $last, $at];
    }

    /**
     * Answers the position of the first character from a position that is not blank.
     */
    public static function blank(string $text, int $at): int
    {
        return $at + strspn($text, " \t\n\r\v\f", $at);
    }

    /**
     * Reads the digits of a transaction number: 0 when there are none, null beyond the largest.
     */
    public static function number(string $digits): ?int
    {
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return 0;
        }
        if (strlen($digits) > 19 || (strlen($digits) === 19 && strcmp($digits, (string) self::LARGEST) > 0)) {
            return null;
        }

        return (int) $digits;
    }

    /**
     * Answers the set with every list of intervals sorted and merged, and without empty UUIDs.
     */
    public function normalized(): self
    {
        $result = [];
        foreach ($this->intervals as $uuid => $tags) {
            foreach ($tags as $tag => $list) {
                usort($list, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
                $merged = [];
                foreach ($list as [$first, $last]) {
                    $top = count($merged) - 1;
                    if ($top >= 0 && $first <= $merged[$top][1] + 1) {
                        $merged[$top] = [$merged[$top][0], max($merged[$top][1], $last)];
                        continue;
                    }
                    $merged[] = [$first, $last];
                }
                if ($merged !== []) {
                    $result[$uuid][$tag] = $merged;
                }
            }
        }

        return new self($result);
    }

    /**
     * Answers the transactions of this set that the other does not hold.
     */
    public function subtract(self $other): self
    {
        $result = [];
        foreach ($this->intervals as $uuid => $tags) {
            foreach ($tags as $tag => $list) {
                $remaining = $list;
                foreach ($other->intervals[$uuid][$tag] ?? [] as [$cutFirst, $cutLast]) {
                    $next = [];
                    foreach ($remaining as [$first, $last]) {
                        if ($cutLast < $first || $cutFirst > $last) {
                            $next[] = [$first, $last];
                            continue;
                        }
                        if ($first < $cutFirst) {
                            $next[] = [$first, $cutFirst - 1];
                        }
                        if ($last > $cutLast) {
                            $next[] = [$cutLast + 1, $last];
                        }
                    }
                    $remaining = $next;
                }
                $result[$uuid][$tag] = $remaining;
            }
        }

        return (new self($result))->normalized();
    }

    /**
     * Tells whether every transaction of this set is in the other.
     */
    public function within(self $other): bool
    {
        return $this->subtract($other)->intervals === [];
    }

    /**
     * Answers the text of the set as the server writes it.
     */
    public function text(): string
    {
        $uuids = $this->intervals;
        ksort($uuids, SORT_STRING);
        $parts = [];
        foreach ($uuids as $uuid => $tags) {
            ksort($tags, SORT_STRING);
            $text = $uuid;
            foreach ($tags as $tag => $list) {
                $text .= $tag === '' ? '' : ':' . $tag;
                foreach ($list as [$first, $last]) {
                    $text .= ':' . $first . ($last > $first ? '-' . $last : '');
                }
            }
            $parts[] = $text;
        }

        return implode(",\n", $parts);
    }
}
