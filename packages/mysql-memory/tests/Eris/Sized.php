<?php

declare(strict_types=1);

namespace Tests\Eris;

use Eris\Generator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Override;

/**
 * Preserves an Eris generator and its shrinking while fixing its generation size.
 *
 * @template V
 * @implements Generator<V>
 */
final class Sized implements Generator
{
    /**
     * @param Generator<V> $generator The original Eris generator
     */
    public function __construct(private readonly Generator $generator, private readonly int $size)
    {
    }

    /**
     * @param int $size The caller's size, replaced by this composition's fixed size
     * @return GeneratedValueSingle<V>
     */
    #[Override]
    public function __invoke($size, \Eris\Random\RandomRange $rand): GeneratedValueSingle
    {
        return ($this->generator)($this->size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The original or shrunk Eris value
     * @return GeneratedValue<V>
     */
    #[Override]
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->generator->shrink($element);
    }
}
