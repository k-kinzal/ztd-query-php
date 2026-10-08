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
 * @visibility MySqlMemory\Evaluation\Function\Pattern
 */
final class Members
{
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
