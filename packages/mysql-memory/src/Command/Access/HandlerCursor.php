<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Dictionary\Key;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Value\Order;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexRead;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerIndexSeek;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\HandlerScan;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\IndexDirection;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\KeyComparison;
use SqlSemantics\Platform\MySql\Statement\Dml\Handler\ScanDirection;

/**
 * Moves the cursor of a HANDLER read through the rows of a table, in its natural order or the order of an index.
 *
 * A read follows its order from where the last read of that order left the cursor; a read of
 * another order starts it afresh. A key seek with = reads the rows equal to the values, >= and >
 * read forward from the first row past the values, and <= and < read backward from the last row
 * before them. The rows a read skips for its WHERE condition move the cursor too.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/handler.html.
 *
 * @visibility MySqlMemory
 */
final class HandlerCursor
{
    /**
     * Answers the numbers of the rows in the order a read follows: the natural order, or the order of an index after it.
     *
     * @param array<int, list<int|float|string|null>> $rows The rows in their natural order
     * @return list<int>
     */
    public function ordered(array $rows, ?Key $key, StoredTable $table): array
    {
        $numbers = array_keys($rows);
        if ($key !== null) {
            usort($numbers, fn (int $left, int $right): int => $this->compare($rows[$left], $key, $table, array_map(static fn (int $column) => $rows[$right][$column], $key->columns)));
        }

        return $numbers;
    }

    /**
     * Compares the leading columns of an index in a row with values, as many columns as there are values.
     *
     * @param list<int|float|string|null> $row
     * @param list<int|float|string|null> $values
     */
    public function compare(array $row, Key $key, StoredTable $table, array $values): int
    {
        foreach ($values as $index => $value) {
            $column = $key->columns[$index];
            $order = Order::compare($row[$column], $value, $table->definition->columns[$column]->domain);
            if ($order !== 0) {
                return $order;
            }
        }

        return 0;
    }

    /**
     * Answers where a read starts in its order and the direction it moves in.
     *
     * @param list<int> $numbers
     * @param array<int, list<int|float|string|null>> $rows
     * @param list<int|float|string|null> $values
     * @param int|null $position Where the cursor stands in the order, or null when the read starts the order afresh
     * @return array{int, int}
     */
    public function start(HandlerScan|HandlerIndexRead|HandlerIndexSeek $statement, array $numbers, array $rows, ?Key $key, StoredTable $table, array $values, ?int $position): array
    {
        $last = count($numbers) - 1;
        if ($statement instanceof HandlerScan) {
            return [$statement->direction === ScanDirection::Next && $position !== null ? $position + 1 : 0, 1];
        }
        if ($statement instanceof HandlerIndexRead) {
            return match ($statement->direction) {
                IndexDirection::First => [0, 1],
                IndexDirection::Last => [$last, -1],
                IndexDirection::Next => [$position === null ? 0 : $position + 1, 1],
                IndexDirection::Previous => [$position === null ? $last : $position - 1, -1],
            };
        }
        assert($key !== null);

        return $this->seek($statement->comparison, array_map(fn (int $number): int => $this->compare($rows[$number], $key, $table, $values), $numbers));
    }

    /**
     * Answers where a key seek starts and the direction it moves in: forward from the first row
     * that satisfies the comparison for = , >= and >, backward from the last one for <= and <.
     *
     * @param list<int> $comparisons The comparison of each row of the order with the values of the seek
     * @return array{int, int}
     */
    public function seek(KeyComparison $comparison, array $comparisons): array
    {
        $satisfies = match ($comparison) {
            KeyComparison::Equal, KeyComparison::GreaterOrEqual => static fn (int $order): bool => $order >= 0,
            KeyComparison::Greater => static fn (int $order): bool => $order > 0,
            KeyComparison::LessOrEqual => static fn (int $order): bool => $order <= 0,
            KeyComparison::Less => static fn (int $order): bool => $order < 0,
        };
        $last = count($comparisons) - 1;
        if ($comparison === KeyComparison::LessOrEqual || $comparison === KeyComparison::Less) {
            $index = $last;
            while ($index >= 0 && !$satisfies($comparisons[$index])) {
                $index--;
            }

            return [$index, -1];
        }
        $index = 0;
        while ($index <= $last && !$satisfies($comparisons[$index])) {
            $index++;
        }

        return [$index, 1];
    }

    /**
     * Reads the rows of an order from where a read starts: it skips the rows the condition rejects
     * and the rows of the offset, and stops after count rows, or at the first row an equality seek does not match.
     *
     * @param array<int, list<int|float|string|null>> $rows The rows in their natural order
     * @param list<int> $numbers The numbers of the rows in the order of the read
     * @param list<int|float|string|null> $values The values of an equality seek
     * @param int|null $count The number of rows the read answers at most, or null when it has no bound
     * @param Key|null $equal The index of an equality seek, or null when the read is not one
     * @return array{list<list<int|float|string|null>>, int|null} The rows read, and where the cursor stopped or null when the read ran out of rows
     */
    public function walk(array $rows, array $numbers, int $start, int $step, ?int $count, int $offset, ?Key $equal, StoredTable $table, array $values, ?Evaluable $condition, Context $context): array
    {
        $found = [];
        $matched = 0;
        $frame = new Frame($context);
        for ($index = $start; $count !== 0 && $index >= 0 && $index < count($numbers); $index += $step) {
            $row = $rows[$numbers[$index]];
            if ($equal !== null && $this->compare($row, $equal, $table, $values) !== 0) {
                return [$found, $index - $step];
            }
            $frame->row = $row;
            if ($condition !== null && Convert::toBool($condition->evaluate($frame), $condition->domain(), $context) !== true) {
                continue;
            }
            $matched++;
            if ($matched > $offset) {
                $found[] = $row;
                if (count($found) === $count) {
                    return [$found, $index];
                }
            }
        }

        return [$found, null];
    }
}
