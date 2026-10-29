<?php

declare(strict_types=1);

namespace Tests\Eris;

use Closure;
use Eris\Generator;
use Eris\Shrinker\FixedTimeLimit;

/**
 * Creates streaming shrinkers for the standard Eris quantifier.
 */
final class ShrinkerFactory
{
    /**
     * @param array{timeLimit: int|null} $options
     */
    public function __construct(private readonly array $options)
    {
    }

    /**
     * @template T
     * @param list<Generator<T>> $generators
     * @param callable(T...): void $assertion
     * @return Shrinker<T>
     */
    public function multiple(array $generators, callable $assertion): Shrinker
    {
        $shrinker = new Shrinker($generators, Closure::fromCallable($assertion));
        if ($this->options['timeLimit'] !== null) {
            $shrinker->setTimeLimit(new FixedTimeLimit($this->options['timeLimit'], 'time'));
        }
        return $shrinker;
    }
}
