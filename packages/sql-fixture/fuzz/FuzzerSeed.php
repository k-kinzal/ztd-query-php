<?php

declare(strict_types=1);

namespace Fuzz;

/**
 * Turns raw fuzzer bytes into a seed a generator can be started from.
 *
 * A fuzzer mutates bytes, and what a target needs is a number it can hand to a
 * random generator so the same input always produces the same fixture. Without
 * that, a crash the fuzzer found could not be reproduced from the input it
 * saved. Very short inputs are padded because a checksum over fewer than four
 * bytes collides often enough to hide distinct cases behind one seed.
 */
final class FuzzerSeed
{
    private const SHORTEST_INPUT = 4;

    /**
     * Answers the seed one fuzzer input stands for.
     *
     * @param string $input Raw fuzzer input
     *
     * @return int Seed to start the generator from
     */
    public function of(string $input): int
    {
        return crc32(strlen($input) < self::SHORTEST_INPUT ? str_pad($input, self::SHORTEST_INPUT, "\0") : $input);
    }
}
