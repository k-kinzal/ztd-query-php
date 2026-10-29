<?php

declare(strict_types=1);

namespace Tests\Eris;

use Closure;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueOptions;
use Eris\Generator\GeneratedValueSingle;
use LogicException;
use Override;
use Traversable;

/**
 * Streams Eris shrink candidates, including mapped semantic publications.
 * A candidate is constructed only when the shrinker reaches it.
 *
 * @template T
 * @extends GeneratedValueOptions<T>
 */
final class Options extends GeneratedValueOptions
{
    /**
     * @param Closure(): Traversable<int, GeneratedValueSingle<T>> $candidates
     */
    public function __construct(private readonly Closure $candidates)
    {
        parent::__construct([]);
    }

    /**
     * @return Traversable<int, GeneratedValueSingle<T>>
     */
    #[Override]
    public function getIterator(): Traversable
    {
        return ($this->candidates)();
    }

    /**
     * @template U
     * @param callable(T): U $callable
     * @param string $generatorName
     * @return Options<U>
     */
    #[Override]
    public function map(callable $callable, $generatorName): self
    {
        return new self(function () use ($callable, $generatorName): Traversable {
            foreach ($this as $candidate) {
                yield $candidate->map($callable, $generatorName);
            }
        });
    }

    /**
     * @template U
     * @param GeneratedValueSingle<U> $value
     * @return Options<T|U>
     */
    #[Override]
    public function add(GeneratedValueSingle $value): self
    {
        return new self(function () use ($value): Traversable {
            foreach ($this as $candidate) {
                yield $candidate;
            }
            yield $value;
        });
    }

    /**
     * @param GeneratedValue<mixed> $value
     * @return Options<T>
     */
    #[Override]
    public function remove(GeneratedValue $value): self
    {
        return new self(function () use ($value): Traversable {
            foreach ($this as $candidate) {
                if ((new GeneratedValueOptions([$candidate]))->remove($value)->count() !== 0) {
                    yield $candidate;
                }
            }
        });
    }

    /**
     * Counts the candidates by visiting them without retaining their publications.
     */
    #[Override]
    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    /**
     * @return GeneratedValueSingle<T>
     * @throws LogicException When there are no shrink candidates
     */
    #[Override]
    public function first(): GeneratedValueSingle
    {
        foreach ($this as $candidate) {
            return $candidate;
        }
        throw new LogicException('An empty set of Eris candidates has no first value.');
    }

    /**
     * @return GeneratedValueSingle<T>
     * @throws LogicException When there are no shrink candidates
     */
    #[Override]
    public function last(): GeneratedValueSingle
    {
        $last = null;
        foreach ($this as $candidate) {
            $last = $candidate;
        }
        return $last ?? throw new LogicException('An empty set of Eris candidates has no last value.');
    }
}
