<?php

declare(strict_types=1);

namespace Tests\Generator;

use Eris\Generator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Random\RandomRange;

/**
 * Delegates generation and shrinking to the Eris composition owned by a type generator.
 *
 * @template T
 */
trait Composition
{
    /** @var Generator<T> */
    private Generator $delegate;

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<T>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<T>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
