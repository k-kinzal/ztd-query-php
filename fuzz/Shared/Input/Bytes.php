<?php

declare(strict_types=1);

namespace Fuzz\Shared\Input;

/**
 * Decodes local choices without hashing away the structure of a counterexample.
 */
final class Bytes
{
    private int $offset = 0;

    /**
     * Keep the raw input bytes for incremental decoding.
     */
    public function __construct(private readonly string $input)
    {
    }

    /**
     * Consume one byte and choose a bounded alternative.
     * @param positive-int $choices
     * @return non-negative-int
     */
    public function next(int $choices = 256): int
    {
        return ord($this->input[$this->offset++] ?? "\0") % $choices;
    }
}
