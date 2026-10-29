<?php

declare(strict_types=1);

namespace Tests\Eris;

use Closure;
use Eris\Generator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueOptions;
use Eris\Generator\GeneratedValueSingle;
use LogicException;
use Override;
use Traversable;
use WeakMap;

/**
 * Rebuilds dependent semantic operands while Eris shrinks either side of their dependency.
 * Prerequisites shrink first so smaller widths, depths and lengths are explored
 * before minimizing every operand of a larger structure.
 *
 * @template A
 * @template B
 * @extends Generator\BindGenerator<A, B>
 */
final class Dependent extends Generator\BindGenerator
{
    /**
     * @var WeakMap<GeneratedValueSingle<B>, array{GeneratedValue<A>, Generator<B>, GeneratedValue<B>, int, int}>
     */
    private readonly WeakMap $generated;

    /**
     * @param Generator<A> $prerequisites
     * @param Closure(A): Generator<B> $dependent
     */
    public function __construct(private readonly Generator $prerequisites, private readonly Closure $dependent)
    {
        parent::__construct($prerequisites, $dependent);
        $this->generated = new WeakMap();
    }

    /**
     * @param int $size The Eris size budget
     * @return GeneratedValueSingle<B>
     */
    #[Override]
    public function __invoke($size, \Eris\Random\RandomRange $rand): GeneratedValueSingle
    {
        $parent = ($this->prerequisites)($size, $rand);
        $seed = $rand->rand(0, 2147483647);
        $random = new \Eris\Random\RandomRange(new \Eris\Random\MersenneTwister());
        $random->seed($seed);
        $generator = ($this->dependent)($parent->unbox());
        $child = $generator($size, $random);
        $result = GeneratedValueSingle::fromValueAndInput($child->unbox(), [$parent, $child], 'bind');
        $this->generated[$result] = [$parent, $generator, $child, $size, $seed];
        return $result;
    }

    /**
     * @param GeneratedValue<mixed> $element An original or shrunk value of this dependency
     * @return GeneratedValue<B>
     * @throws LogicException When the value was produced by another generator
     */
    #[Override]
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        $record = $this->generated[$element] ?? throw new LogicException('A dependent value retains its original Eris generators.');
        [$parent, $generator, $child, $size, $seed] = $record;
        return new Options(function () use ($record, $parent, $generator, $child, $size, $seed): Traversable {
            $emitted = false;
            foreach ($this->prerequisites->shrink($parent) as $candidate) {
                if ((new GeneratedValueOptions([$candidate]))->remove($parent)->count() === 0) {
                    continue;
                }
                $dependent = ($this->dependent)($candidate->unbox());
                $random = new \Eris\Random\RandomRange(new \Eris\Random\MersenneTwister());
                $random->seed($seed);
                $rebuilt = $dependent($size, $random);
                $result = GeneratedValueSingle::fromValueAndInput($rebuilt->unbox(), [$candidate, $rebuilt], 'bind');
                $this->generated[$result] = [$candidate, $dependent, $rebuilt, $size, $seed];
                $emitted = true;
                yield $result;
            }
            foreach ($generator->shrink($child) as $candidate) {
                if ((new GeneratedValueOptions([$candidate]))->remove($child)->count() === 0) {
                    continue;
                }
                $result = GeneratedValueSingle::fromValueAndInput($candidate->unbox(), [$parent, $candidate], 'bind');
                $this->generated[$result] = [$parent, $generator, $candidate, $size, $seed];
                $emitted = true;
                yield $result;
            }
            if ($emitted) {
                return;
            }
            $unchanged = GeneratedValueSingle::fromValueAndInput($child->unbox(), [$parent, $child], 'bind');
            $this->generated[$unchanged] = $record;
            yield $unchanged;
        });
    }
}
