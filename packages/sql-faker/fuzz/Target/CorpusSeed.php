<?php

declare(strict_types=1);

namespace Fuzz\Target;

/**
 * Turns raw fuzzer input into a deterministic RNG seed.
 *
 * php-fuzzer mutates arbitrary byte strings, including strings shorter than the
 * four bytes crc32() needs to spread its output over the whole range, so short
 * input is padded before it is hashed. The same bytes always produce the same
 * seed, which is what makes a reported finding reproducible from its corpus
 * entry alone.
 */
final class CorpusSeed
{
    /**
     * Derives the RNG seed that a generator run should use for the given input.
     *
     * @param string $input Raw fuzzer input (mutated bytes)
     *
     * @return int Seed to hand to the Faker generator
     */
    public function forInput(string $input): int
    {
        if (strlen($input) < 4) {
            $input = str_pad($input, 4, "\0");
        }

        return crc32($input);
    }
}
