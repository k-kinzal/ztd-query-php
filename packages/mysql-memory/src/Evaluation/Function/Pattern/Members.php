<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Function\Pattern;

/**
 * A set of characters of a regular expression, written for PCRE.
 *
 * A plain set is the items of a PCRE class, ranges and properties, possibly complemented; a set
 * built by intersection or difference, or a union with a complemented set, is a PCRE expression
 * that matches one character of it by lookahead.
 *
 * Matched without regard to case, ICU closes the set of a property or a class over case before it
 * complements it or combines it with another set: the set holds every character whose simple case
 * folding is that of a member, so that \p{Lu} matches every cased letter that has an upper-case
 * form, and \P{Lu} only what neither is upper case nor has an upper-case form (verified on a live
 * 8.4 server). PCRE treats properties without regard to case in ways that differ between its
 * versions, so a closed set is written out and matched with regard to case.
 * Source: https://unicode-org.github.io/icu/userguide/strings/unicodeset.html#case-closure.
 *
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Members
{
    /**
     * The characters that share their simple case folding with another, in groups of those that share one; listed once.
     *
     * @var list<list<string>>|null
     */
    public static ?array $cases = null;

    /**
     * The PCRE expression of each set closed over case, by the expression of the set.
     *
     * @var array<string, string>
     */
    public static array $closed = [];

    /**
     * @param list<string> $items The items of a PCRE class
     * @param bool $negated Whether the set is the complement of the items
     * @param string|null $expression A PCRE expression that matches one character of a set that is not plain
     */
    public function __construct(public readonly array $items, public readonly bool $negated = false, public readonly ?string $expression = null)
    {
    }

    /**
     * Answers the set of one character.
     */
    public static function character(string $character): self
    {
        return new self([self::escape($character)]);
    }

    /**
     * Answers the set of the characters between two, both included.
     */
    public static function range(string $first, string $last): self
    {
        return new self([self::escape($first) . '-' . self::escape($last)]);
    }

    /**
     * Writes a character for PCRE: an ASCII letter or digit as itself, any other by its code point.
     */
    public static function escape(string $character): string
    {
        return preg_match('/\A[A-Za-z0-9]\z/', $character) === 1 ? $character : sprintf('\x{%X}', mb_ord($character, 'UTF-8'));
    }

    /**
     * Answers the union of sets.
     *
     * @param list<self> $sets
     */
    public static function union(array $sets): self
    {
        $items = [];
        $others = [];
        foreach ($sets as $set) {
            if ($set->expression === null && !$set->negated) {
                array_push($items, ...$set->items);
            } else {
                $others[] = $set->source();
            }
        }
        if ($others === []) {
            return new self($items);
        }
        if ($items !== []) {
            array_unshift($others, (new self($items))->source());
        }

        return count($others) === 1 ? new self([], false, $others[0]) : new self([], false, '(?:' . implode('|', $others) . ')');
    }

    /**
     * Answers the complement of the set.
     */
    public function complement(): self
    {
        if ($this->expression === null) {
            return new self($this->items, !$this->negated);
        }

        return new self([], false, '(?:(?!' . $this->expression . ')(?s:.))');
    }

    /**
     * Answers the characters of both sets.
     */
    public function intersect(self $other): self
    {
        return new self([], false, '(?:(?=' . $this->source() . ')' . $other->source() . ')');
    }

    /**
     * Answers the characters of the set that the other set does not hold.
     */
    public function minus(self $other): self
    {
        return new self([], false, '(?:(?!' . $other->source() . ')' . $this->source() . ')');
    }

    /**
     * Answers the set closed over case, matched with regard to case: the set and every character whose simple case folding is that of a member.
     */
    public function closed(): self
    {
        $source = $this->source();
        if (!isset(self::$closed[$source])) {
            $cases = self::cases();
            preg_match_all('/' . $source . '/u', implode('', array_merge(...$cases)), $matches);
            $members = array_flip($matches[0]);
            $added = [];
            foreach ($cases as $group) {
                $outside = array_values(array_filter($group, static fn (string $character): bool => !isset($members[$character])));
                if (count($outside) < count($group)) {
                    array_push($added, ...array_map(static fn (string $character): int => mb_ord($character, 'UTF-8'), $outside));
                }
            }
            sort($added);
            $ranges = implode('', array_map(static fn (array $run): string => self::escape(mb_chr($run[0], 'UTF-8')) . ($run[0] === $run[1] ? '' : '-' . self::escape(mb_chr($run[1], 'UTF-8'))), (new Listing())->runs($added)));
            self::$closed[$source] = '(?-i:' . match (true) {
                $ranges === '' => $source,
                $this->expression === null && !$this->negated => '[' . implode('', $this->items) . $ranges . ']',
                default => '(?:' . $source . '|[' . $ranges . '])',
            } . ')';
        }

        return new self([], false, self::$closed[$source]);
    }

    /**
     * Answers the set matched with regard to case, as it is.
     */
    public function exact(): self
    {
        return new self([], false, '(?-i:' . $this->source() . ')');
    }

    /**
     * Answers the characters that share their simple case folding with another, in groups of those that share one, listed once for each process.
     *
     * @return list<list<string>>
     */
    public static function cases(): array
    {
        if (self::$cases === null) {
            $groups = [];
            for ($code = 0x41; $code <= 0x1FFFF; $code++) {
                if ($code >= 0xD800 && $code <= 0xDFFF) {
                    continue;
                }
                $character = mb_chr($code, 'UTF-8');
                $groups[mb_convert_case($character, MB_CASE_FOLD_SIMPLE, 'UTF-8')][] = $character;
            }
            self::$cases = array_values(array_filter($groups, static fn (array $group): bool => count($group) > 1));
        }

        return self::$cases;
    }

    /**
     * Answers the PCRE expression that matches one character of the set.
     */
    public function source(): string
    {
        if ($this->expression !== null) {
            return $this->expression;
        }
        if ($this->items === []) {
            return $this->negated ? '(?s:.)' : '(?!)';
        }

        return ($this->negated ? '[^' : '[') . implode('', $this->items) . ']';
    }
}
