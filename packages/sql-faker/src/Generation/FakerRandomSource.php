<?php

declare(strict_types=1);

namespace SqlFaker\Generation;

use Faker\Generator as FakerGenerator;
use SqlFaker\Contract\RandomSource;

final class FakerRandomSource implements RandomSource
{
    public function __construct(
        private readonly FakerGenerator $faker,
    ) {
    }

    public function seed(int $seed): void
    {
        $this->faker->seed($seed);
    }

    public function numberBetween(int $min, int $max): int
    {
        return $this->faker->numberBetween($min, $max);
    }

    public function stringElement(array $elements): string
    {
        if (count($elements) === 1) {
            return $elements[0];
        }

        /** @var string $value */
        $value = $this->faker->randomElement($elements);

        return $value;
    }
}
