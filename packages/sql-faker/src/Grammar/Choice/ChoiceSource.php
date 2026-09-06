<?php

declare(strict_types=1);

namespace SqlFaker\Grammar\Choice;

use Faker\Generator;
use InvalidArgumentException;

/**
 * Supplies lexical choices from Faker or a generation-local byte stream.
 *
 * @visibility root
 */
final class ChoiceSource
{
    private ?ByteChoices $lexical = null;

    /**
     * Retains the ordinary Faker source between generation calls.
     */
    public function __construct(private readonly Generator $faker)
    {
    }

    /**
     * Starts a fresh stream, including for empty byte input.
     */
    public function begin(?string $bytes): void
    {
        $this->lexical = $bytes === null ? null : new ByteChoices($bytes);
    }

    /**
     * Releases the generation-local cursor.
     */
    public function end(): void
    {
        $this->lexical = null;
    }

    /**
     * Chooses an integer without returning byte-driven generation to global RNG.
     *
     * Exhausted lexical streams deterministically choose the lower bound.
     *
     * @throws InvalidArgumentException When the range is reversed
     */
    public function numberBetween(int $min, int $max): int
    {
        if ($max < $min) {
            throw new InvalidArgumentException('Choice bounds must be ordered.');
        }
        if ($this->lexical === null) {
            return $this->faker->numberBetween($min, $max);
        }
        if ($min < 0 && $max > PHP_INT_MAX + $min) {
            return ($this->lexical->index(2) ?? 0) === 0
                ? $this->numberBetween($min, -1)
                : $this->numberBetween(0, $max);
        }
        $span = $max - $min;
        if ($span === PHP_INT_MAX) {
            $value = $this->lexical->index(PHP_INT_MAX) ?? 0;
            return $min + (($this->lexical->index(2) ?? 0) === 0 ? $value : PHP_INT_MAX);
        }
        return $min + ($this->lexical->index($span + 1) ?? 0);
    }
}
