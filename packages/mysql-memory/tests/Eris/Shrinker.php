<?php

declare(strict_types=1);

namespace Tests\Eris;

use Closure;
use Eris\Generator;
use Eris\Generator\GeneratedValueOptions;
use Eris\Generator\GeneratedValueSingle;
use Eris\Quantifier\Evaluation;
use Eris\Shrinker\NoTimeLimit;
use Eris\Shrinker\TimeLimit;
use LogicException;
use PHPUnit\Framework\AssertionFailedError;
use RuntimeException;

/**
 * Uses Eris evaluation and shrink candidates without retaining every published
 * sibling. Successful reductions restart at their own first candidate, as in
 * Eris's multiple shrinker; antecedents, listeners and time limits still apply.
 *
 * @template T
 */
final class Shrinker
{
    /** @var Generator<list<T>> */
    private readonly Generator $generator;
    /** @var list<callable(GeneratedValueSingle<list<T>>): bool> */
    private array $conditions = [];
    /** @var list<callable(GeneratedValueSingle<list<T>>): void> */
    private array $listeners = [];
    private TimeLimit $timeLimit;

    /**
     * @param list<Generator<T>> $generators
     * @param Closure(T...): void $assertion
     */
    public function __construct(array $generators, private readonly Closure $assertion)
    {
        $this->generator = \Tests\Generator\Operands::tuple(...$generators);
        $this->timeLimit = new NoTimeLimit();
    }

    /**
     * @return $this
     */
    public function setTimeLimit(TimeLimit $timeLimit): self
    {
        $this->timeLimit = $timeLimit;
        return $this;
    }

    /**
     * @param callable(GeneratedValueSingle<list<T>>): bool $condition
     * @return $this
     */
    public function addGoodShrinkCondition(callable $condition): self
    {
        $this->conditions[] = $condition;
        return $this;
    }

    /**
     * @param callable(GeneratedValueSingle<list<T>>): void $listener
     * @return $this
     */
    public function onAttempt(callable $listener): self
    {
        $this->listeners[] = $listener;
        return $this;
    }

    /**
     * @param GeneratedValueSingle<list<T>> $elements
     * @param AssertionFailedError $exception
     * @throws AssertionFailedError The smallest encountered property failure
     * @throws RuntimeException When the configured Eris shrinking time limit expires
     * @throws LogicException When Eris cannot construct an evaluation
     */
    public function from(GeneratedValueSingle $elements, AssertionFailedError $exception): never
    {
        $this->timeLimit->start();
        do {
            $reduced = false;
            foreach ($this->generator->shrink($elements) as $candidate) {
                if ($this->timeLimit->hasBeenReached()) {
                    throw new RuntimeException('Eris reached the shrinking time limit (' . $this->timeLimit . ').', -1, $exception);
                }
                if ((new GeneratedValueOptions([$candidate]))->remove($elements)->count() === 0
                    || array_filter($this->conditions, static fn (callable $condition): bool => !$condition($candidate)) !== []) {
                    continue;
                }
                foreach ($this->listeners as $listener) {
                    $listener($candidate);
                }
                $evaluation = Evaluation::of($this->assertion);
                if (!$evaluation instanceof Evaluation) {
                    throw new LogicException('Eris creates an evaluation for the generated assertion.');
                }
                $evaluation->with($candidate);
                $evaluation->onFailure(static function (GeneratedValueSingle $value, AssertionFailedError $failure) use (&$elements, &$exception, &$reduced): void {
                    $elements = $value;
                    $exception = $failure;
                    $reduced = true;
                });
                $evaluation->execute();
                if ($reduced) {
                    break;
                }
            }
        } while ($reduced);
        throw $exception;
    }
}
