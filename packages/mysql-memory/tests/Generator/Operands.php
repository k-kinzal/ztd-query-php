<?php

declare(strict_types=1);

namespace Tests\Generator;

use Closure;
use Eris\Generator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueOptions;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generator\TupleGenerator;
use Eris\Generators;
use LogicException;
use Override;
use WeakMap;

/**
 * Composes constructor operands with Eris, shrinking one operand at a time.
 * Eris's tuple shrinker eagerly multiplies all alternatives, including constants;
 * nested semantic records otherwise exhaust memory before reporting a failure.
 */
final class Operands
{
    /**
     * @param mixed ...$operands Constructor operands or Eris generators
     * @return Generator<list<mixed>>
     */
    public static function tuple(...$operands): Generator
    {
        $generators = array_map(static fn ($operand): Generator => $operand instanceof Generator ? $operand : Generators::constant($operand), array_values($operands));
        return new /**
         * @template T
         * @extends TupleGenerator<mixed>
         */ class ($generators) extends TupleGenerator {
            /**
             * @param list<Generator<T>> $operands
             */
            public function __construct(private readonly array $operands)
            {
                parent::__construct($operands);
            }

            /**
             * @param GeneratedValue<mixed> $tuple
             * @return GeneratedValue<list<mixed>>
             * @throws LogicException When the input was not produced by this tuple
             */
            #[Override]
            public function shrink(GeneratedValue $tuple): GeneratedValue
            {
                $input = $tuple->input();
                $original = $tuple->unbox();
                if (!is_array($input) || !array_is_list($input) || count($input) !== count($this->operands)) {
                    throw new LogicException('Constructor operands require a matching Eris tuple input.');
                }
                if (!is_array($original) || !array_is_list($original) || count($original) !== count($input)) {
                    throw new LogicException('Constructor operands require a matching Eris tuple value.');
                }
                foreach ($input as $value) {
                    if (!$value instanceof GeneratedValue) {
                        throw new LogicException('Constructor operands retain their Eris generation inputs.');
                    }
                }
                $options = [];
                foreach ($this->operands as $index => $generator) {
                    foreach ($generator->shrink($input[$index]) as $candidate) {
                        if ((new GeneratedValueOptions([$candidate]))->remove($input[$index])->count() === 0) {
                            continue;
                        }
                        $values = $original;
                        $values[$index] = $candidate->unbox();
                        $inputs = $input;
                        $inputs[$index] = $candidate;
                        $options[] = GeneratedValueSingle::fromValueAndInput(array_values($values), $inputs, 'tuple');
                    }
                }
                /** Eris's frequency generator unboxes even an irreducible result. */
                return $options === [] ? GeneratedValueSingle::fromValueAndInput($original, $input, 'tuple') : new GeneratedValueOptions($options);
            }
        };
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
        return new /**
         * @template U
         * @extends \Eris\Generator\OneOfGenerator<U>
         */ class ($alternatives) extends Generator\OneOfGenerator {
            /**
             * @param non-empty-list<Generator<U>> $alternatives
             */
            public function __construct(private readonly array $alternatives)
            {
                parent::__construct($alternatives);
            }

            /**
             * @param GeneratedValue<mixed> $element
             * @return GeneratedValue<U>
             * @throws LogicException When the value is outside the Eris alternative protocol
             */
            #[Override]
            public function shrink(GeneratedValue $element): GeneratedValue
            {
                $input = $element->input();
                if (!is_array($input) || !isset($input['generator'], $input['value']) || !is_int($input['generator']) || !isset($this->alternatives[$input['generator']]) || !$input['value'] instanceof GeneratedValue) {
                    throw new LogicException('An alternative retains the Eris choice and its generation input.');
                }
                $index = $input['generator'];
                $options = [];
                foreach ($this->alternatives[$index]->shrink($input['value']) as $candidate) {
                    $options[] = GeneratedValueSingle::fromValueAndInput($candidate->unbox(), ['value' => $candidate, 'generator' => $index], 'frequency');
                }
                if ($options === []) {
                    throw new LogicException('Eris alternatives retain at least their irreducible input.');
                }
                return new GeneratedValueOptions($options);
            }
        };
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
        return new /**
         * @template A
         * @template B
         * @extends Generator\BindGenerator<A, B>
         */ class ($prerequisites, Closure::fromCallable($dependent)) extends Generator\BindGenerator {
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
                $options = [];
                foreach ($generator->shrink($child) as $candidate) {
                    if ((new GeneratedValueOptions([$candidate]))->remove($child)->count() === 0) {
                        continue;
                    }
                    $result = GeneratedValueSingle::fromValueAndInput($candidate->unbox(), [$parent, $candidate], 'bind');
                    $this->generated[$result] = [$parent, $generator, $candidate, $size, $seed];
                    $options[] = $result;
                }
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
                    $options[] = $result;
                }
                if ($options !== []) {
                    return new GeneratedValueOptions($options);
                }
                $unchanged = GeneratedValueSingle::fromValueAndInput($child->unbox(), [$parent, $child], 'bind');
                $this->generated[$unchanged] = $record;
                return $unchanged;
            }
        };
    }

}
