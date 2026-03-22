<?php

declare(strict_types=1);

namespace SqlFaker\Contract;

interface RandomSource
{
    public function seed(int $seed): void;

    public function numberBetween(int $min, int $max): int;

    /**
     * @param non-empty-list<string> $elements
     */
    public function stringElement(array $elements): string;
}
