<?php

declare(strict_types=1);

namespace Tests\Generator;

use Closure;
use Eris\Generator;
use Eris\Generators;

/**
 * Composes constructor operands with Eris, shrinking one operand at a time.
 * Eris's tuple shrinker eagerly multiplies all alternatives, including constants;
 * nested semantic records otherwise exhaust memory before reporting a failure.
 */
final class Operands
{
    /**
     * Uses a fixed Eris generation size while retaining the generator's own shrinking.
     *
     * @template T
     * @param Generator<T> $generator The composed value generator
     * @return Generator<T>
     */
    public static function sized(Generator $generator, int $size): Generator
    {
        return new \Tests\Eris\Sized($generator, $size);
    }

    /**
     * @param mixed ...$operands Constructor operands or Eris generators
     * @return Generator<list<mixed>>
     */
    public static function tuple(...$operands): Generator
    {
        $generators = array_map(static fn ($operand): Generator => $operand instanceof Generator ? $operand : Generators::constant($operand), array_values($operands));
        return new \Tests\Eris\Tuple($generators);
    }

    /**
     * Generates a fixed-width row and shrinks one element at a time. Eris's
     * vector delegates to its Cartesian tuple shrinker, so nested rows need the
     * same composition as constructor operands.
     *
     * @template T
     * @param Generator<T> $elements
     * @return Generator<list<T>>
     */
    public static function vector(int $size, Generator $elements): Generator
    {
        return self::tuple(...($size > 0 ? array_fill(0, $size, $elements) : []));
    }

    /**
     * Generates a size-bounded sequence through Eris. Length and element values
     * both shrink, retaining the other inputs when removing an element instead
     * of multiplying every element's shrink candidates.
     *
     * @template T
     * @param Generator<T> $elements
     * @return Generator<list<T>>
     */
    public static function seq(Generator $elements): Generator
    {
        return new \Tests\Eris\Sequence($elements);
    }
    /**
     * Selects an Eris alternative without discarding its nested shrink candidates.
     * Eris's frequency wrapper otherwise unboxes only the last candidate.
     *
     * @template T
     * @param non-empty-list<Generator<T>> $alternatives SQL forms satisfying one role
     * @return Generator<T>
     */
    public static function oneOf(array $alternatives): Generator
    {
        return new \Tests\Eris\Alternative($alternatives);
    }

    /**
     * Composes a dependent Eris generator and shrinks both sides of the dependency.
     * Eris's BindGenerator shrinks only the dependent value. When a prerequisite
     * shrinks, replaying the dependent generator with its original random seed
     * rebuilds coherent operands for the smaller prerequisite.
     *
     * @template T
     * @template U
     * @param Generator<T> $prerequisites Generated values the next generator depends on
     * @param callable(T): Generator<U> $dependent Composes the dependent operands
     * @return Generator<U>
     */
    public static function bind(Generator $prerequisites, callable $dependent): Generator
    {
        return new \Tests\Eris\Dependent($prerequisites, Closure::fromCallable($dependent));
    }

}
