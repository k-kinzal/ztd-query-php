<?php

declare(strict_types=1);

namespace SqlFaker\Generation\Plan;

use Closure;
use InvalidArgumentException;
use SqlFaker\Generation\Value\IntegerDomain;

/**
 * A constructive set of token spellings, independent of their position in a statement.
 *
 * @visibility public
 * @example Constrain an identifier without fixing other identifiers
 *     $names = \SqlFaker\Generation\Plan\LexemeConstraint::oneOf('users', 'orders');
 *     $names->accepts('users') // => true
 *     $names->accepts('products') // => false
 */
final class LexemeConstraint
{
    /**
     * An empty spelling set selects the integer interval instead.
     * @param list<string> $values
     * @throws InvalidArgumentException When the integer interval is empty or negative
     */
    public function __construct(private readonly array $values, private readonly int $minimum = 0, private readonly int $maximum = PHP_INT_MAX)
    {
        if ($minimum < 0 || $maximum < $minimum) {
            throw new InvalidArgumentException('Require 0 <= minimum <= maximum for an integer token.');
        }
    }

    /**
     * Keeps every supplied spelling reachable, including quoted names and literals.
     */
    public static function oneOf(string $first, string ...$others): self
    {
        return new self(array_values(array_unique([$first, ...$others])));
    }

    /**
     * Bounds an unsigned integer token; signs remain part of the surrounding grammar.
     * @throws InvalidArgumentException When the interval is empty or negative
     */
    public static function integers(int $minimum, int $maximum): self
    {
        return new self([], $minimum, $maximum);
    }

    /**
     * @param Closure(positive-int): int $choose
     */
    public function choose(Closure $choose): string
    {
        if ($this->values !== []) {
            return $this->values[$choose(count($this->values))];
        }
        return (new IntegerDomain((string) $this->minimum, (string) $this->maximum, 0))->choose($choose);
    }

    /**
     * Checks an exact spelling without coercing quoted strings into numbers.
     */
    public function accepts(string $value): bool
    {
        if ($this->values !== []) {
            return in_array($value, $this->values, true);
        }
        $domain = new IntegerDomain((string) $this->minimum, (string) $this->maximum, 0);
        return in_array(strlen($value), $domain->match($value), true);
    }

    /**
     * Intersects conditions constructively, without generating and rejecting samples.
     * @throws InvalidArgumentException When the conditions have no common spelling
     */
    public function intersect(self $other): self
    {
        if ($this->values !== [] || $other->values !== []) {
            $values = array_values(array_filter($this->values !== [] ? $this->values : $other->values, fn (string $value): bool => $this->accepts($value) && $other->accepts($value)));
            if ($values === []) {
                throw new InvalidArgumentException('Lexeme constraints have no common spelling.');
            }
            return new self($values);
        }
        return self::integers(max($this->minimum, $other->minimum), min($this->maximum, $other->maximum));
    }
}
