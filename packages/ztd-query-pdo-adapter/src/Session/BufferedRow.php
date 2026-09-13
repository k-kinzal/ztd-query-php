<?php

declare(strict_types=1);

namespace ZtdQuery\Adapter\Pdo\Session;

use PDO;
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
}
