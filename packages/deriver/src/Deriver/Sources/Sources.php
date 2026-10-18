<?php

declare(strict_types=1);

namespace Deriver\Deriver\Sources;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Deriver\Exception\InvalidInputException;
use IteratorAggregate;
use OutOfBoundsException;
use RuntimeException;

/**
 * An ordered, read-only collection of sources with unique paths.
 * @implements ArrayAccess<int, Source>
 * @implements IteratorAggregate<int, Source>
 * @visibility root
 * @example Accessing a captured source
 *     $sources = new \Deriver\Deriver\Sources\Sources([new \Deriver\Deriver\Sources\Source('app.php', '<?php')]);
 *     $sources[0]->path // => 'app.php'
 */
final class Sources implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * @param list<Source> $sources Captured sources in caller order
     * @throws InvalidInputException If paths are empty or duplicated after normalization
     */
    public function __construct(private readonly array $sources)
    {
        $seen = [];
        foreach ($sources as $source) {
            $path = Path::normalize($source->path);
            if ($path === '' || isset($seen[$path])) {
                throw new InvalidInputException('Source paths must be nonempty and unique: ' . $path);
            }
            $seen[$path] = true;
        }
    }

    /**
     * Tests whether an integer offset identifies a source.
     * @param int|string|null $offset Offset supplied through ArrayAccess
     */
    public function offsetExists(mixed $offset): bool
    {
        return is_int($offset) && isset($this->sources[$offset]);
    }

    /**
     * Returns a source by its zero-based integer offset.
     * @param int|string|null $offset Offset supplied through ArrayAccess
     * @throws OutOfBoundsException If the offset is not an existing integer offset
     */
    public function offsetGet(mixed $offset): Source
    {
        if (!is_int($offset) || !isset($this->sources[$offset])) {
            throw new OutOfBoundsException('No source at the supplied offset; use an existing integer offset.');
        }
        return $this->sources[$offset];
    }

    /**
     * @throws RuntimeException Source collections cannot be changed
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new RuntimeException('Sources is read-only.');
    }

    /**
     * @throws RuntimeException Source collections cannot be changed
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new RuntimeException('Sources is read-only.');
    }

    /**
     * Returns the number of sources.
     */
    public function count(): int
    {
        return count($this->sources);
    }

    /**
     * @return ArrayIterator<int, Source> Sources in caller order
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->sources);
    }
}
