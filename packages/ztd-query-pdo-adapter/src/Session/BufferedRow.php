<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use PDO;
use ReflectionException;
use ReflectionObject;
use stdClass;

/**
 * Shapes a row ZTD buffered into what a fetch mode asks for.
 *
 * A simulated statement never reaches the driver, so nothing shapes its rows
 * the way PDO's own fetch modes would. The rows come back keyed by column; this
 * turns one of them into the array, list or object the caller asked to fetch.
 *
 *
 * @visibility ZtdQuery\Adapter\Pdo
 */
final class BufferedRow
{
    /**
     * Answers the row in the shape a fetch mode asks for.
     *
     * A mode this does not know is read as PDO reads an unknown one: both ways
     * at once, which is what PDO::FETCH_BOTH means.
     *
     * @template TValue
     * @param array<string, TValue> $row Row as ZTD buffered it, keyed by column
     * @param int $mode One of PDO::FETCH_*
     *
     * @return array<int|string, TValue>|TValue|stdClass|false The row or column selected by the fetch mode
     */
    public function inMode(array $row, int $mode): mixed
    {
        return match ($mode) {
            PDO::FETCH_ASSOC, PDO::FETCH_NAMED => $row,
            PDO::FETCH_NUM => array_values($row),
            PDO::FETCH_OBJ => (object) $row,
            PDO::FETCH_COLUMN => array_values($row)[0] ?? false,
            default => $this->keyedBothWays($row),
        };
    }

    /**
     * Answers the row keyed by column name and by position at once.
     *
     * @template TValue
     * @param array<string, TValue> $row Row as ZTD buffered it, keyed by column
     *
     * @return array<int|string, TValue> The same values, reachable under either key
     */
    public function keyedBothWays(array $row): array
    {
        $both = [];
        $index = 0;
        foreach ($row as $column => $value) {
            $both[$column] = $value;
            $both[$index] = $value;
            $index++;
        }

        return $both;
    }
    /**
     * Resolve FETCH_DEFAULT using statement options before connection options.
     */
    public function resolveMode(int $mode, int $connectionMode, ?int $statementMode): int
    {
        return $mode === PDO::FETCH_DEFAULT ? ($statementMode ?? $connectionMode) : $mode;
    }

    /**
     * Shape a buffered row while preserving the exhausted-cursor marker.
     *
     * @template TValue
     * @param array<string, TValue>|false $row
     * @return array<int|string, TValue>|TValue|stdClass|false
     */
    public function fetch(array|false $row, int $mode): mixed
    {
        return $row === false ? false : $this->inMode($row, $mode);
    }

    /**
     * Shape all remaining rows, including column selection arguments.
     *
     * @template TValue
     * @template TArgument
     * @param array<int, array<string, TValue>> $rows
     * @param array<TArgument> $arguments
     * @return list<array<int|string, TValue>|TValue|stdClass|false>
     */
    public function all(array $rows, int $mode, array $arguments): array
    {
        $shaped = [];
        $column = is_int($arguments[0] ?? null) ? $arguments[0] : 0;
        foreach ($rows as $row) {
            $shaped[] = $mode === PDO::FETCH_COLUMN
                ? $this->column($row, $column)
                : $this->inMode($row, $mode);
        }
        return $shaped;
    }

    /**
     * Read one positional column while preserving the exhausted-cursor marker.
     *
     * @template TValue
     * @param array<string, TValue>|false $row
     * @return TValue|false
     */
    public function column(array|false $row, int $column): mixed
    {
        return $row === false ? false : (array_values($row)[$column] ?? false);
    }

    /**
     * Hydrate a buffered row after invoking its constructor.
     *
     * @template TValue
     * @template TObject of object
     * @template TArgument
     * @param array<string, TValue>|false $row
     * @param class-string<TObject>|null $class
     * @param array<TArgument> $constructorArgs
     * @return ($class is null ? stdClass : TObject)|false
     * @throws ReflectionException When a declared property cannot be written.
     */
    public function object(array|false $row, ?string $class, array $constructorArgs): object|false
    {
        if ($row === false) {
            return false;
        }
        $resolvedClass = $class ?? stdClass::class;
        $object = new $resolvedClass(...$constructorArgs);
        if ($object instanceof stdClass) {
            foreach ($row as $property => $value) {
                $object->{$property} = $value;
            }
            return $object;
        }
        $reflection = new ReflectionObject($object);
        foreach ($row as $property => $value) {
            if ($reflection->hasProperty($property)) {
                $reflection->getProperty($property)->setValue($object, $value);
            }
        }
        return $object;
    }
}
