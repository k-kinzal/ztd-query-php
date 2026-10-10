<?php

declare(strict_types=1);

namespace MySqlMemory\Storage;

use MySqlMemory\Dictionary\Partition\Partitioning;
use MySqlMemory\Dictionary\Partition\PartitionMethod;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\PartitionError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Order;

/**
 * Finds the partition a row of a partitioned table lands in.
 *
 * A RANGE row lands in the first partition whose bound is above its value, NULL in the first
 * partition; a LIST row in the partition that lists its value, NULL included. A HASH row lands in
 * the partition its value modulo the number of partitions names, the absolute value of a
 * negative one, and NULL as 2^63; LINEAR HASH masks the value by the powers of two at or above the
 * number of partitions. A row no RANGE or LIST partition holds is refused
 * (ER_NO_PARTITION_FOR_GIVEN_VALUE). Every rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-types.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-linear-hash.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-handling-nulls.html.
 *
 * @visibility MySqlMemory
 */
final class Partitions
{
    /**
     * @param TableDefinition $definition The partitioned table
     * @param Partitioning $partitioning How it is partitioned
     * @param Context $context The statement reading or writing the rows
     */
    public function __construct(public readonly TableDefinition $definition, public readonly Partitioning $partitioning, public readonly Context $context)
    {
    }

    /**
     * Answers the positions of the partitions a PARTITION clause names, in the order of the table.
     *
     * @param list<\SqlSemantics\Statement\Identifier\Name> $names
     * @return list<int>
     *
     * @throws SqlError When the table has no partition of a name
     */
    public function selected(array $names): array
    {
        $positions = [];
        foreach ($names as $name) {
            $positions[] = $this->partitioning->partition($name->value) ?? throw \MySqlMemory\Error\Family\SchemaError::UnknownPartition->error($name->value, $this->definition->name);
        }
        $positions = array_values(array_unique($positions));
        sort($positions);

        return $positions;
    }

    /**
     * Refuses a row a write would place outside the partitions it names (ER_ROW_DOES_NOT_MATCH_GIVEN_PARTITION_SET), or in no partition.
     *
     * @param list<int|float|string|null> $row
     * @param list<int>|null $selected The partitions the write names, or null when it names none
     *
     * @throws SqlError When the row is refused
     */
    public function place(array $row, ?array $selected): void
    {
        if ($this->partitioning->method === PartitionMethod::Key && $selected === null) {
            return;
        }
        $partition = $this->locate($row);
        if ($selected !== null && !in_array($partition, $selected, true)) {
            throw PartitionError::RowNotInPartitions->error();
        }
    }

    /**
     * Answers rows in the order a scan reads them: partition by partition, each in the order it holds them, keeping only the partitions selected.
     *
     * @param array<int, list<int|float|string|null>> $rows The rows by number, in the order of the clustered index
     * @param list<int>|null $selected The partitions read, or null for every one
     * @return array<int, list<int|float|string|null>>
     *
     * @throws SqlError When the partitioning is KEY
     */
    public function ordered(array $rows, ?array $selected): array
    {
        $parts = [];
        foreach ($rows as $number => $row) {
            $partition = $this->locate($row);
            if ($selected === null || in_array($partition, $selected, true)) {
                $parts[$partition][$number] = $row;
            }
        }
        ksort($parts);
        $ordered = [];
        foreach ($parts as $part) {
            $ordered += $part;
        }

        return $ordered;
    }

    /**
     * Answers the position of the partition a row lands in.
     *
     * @param list<int|float|string|null> $row
     *
     * @throws SqlError When no partition holds the row, or the partitioning is KEY
     */
    public function locate(array $row): int
    {
        $partitioning = $this->partitioning;
        $value = $partitioning->expression?->evaluate(new Frame($this->context, $row));
        $tuple = array_map(static fn (int $position) => $row[$position], $partitioning->columns);
        $found = match ($partitioning->method) {
            PartitionMethod::Range => $this->range($value === null ? null : [$value], $value === null),
            PartitionMethod::RangeColumns => $this->range($tuple, false),
            PartitionMethod::List, PartitionMethod::ListColumns => $this->listed($partitioning->method === PartitionMethod::List ? [$value] : $tuple),
            PartitionMethod::Hash => $this->hash($value),
            PartitionMethod::Key => throw StatementError::NotSupportedYet->error('the partitions of a table partitioned by KEY'),
        };
        if ($found === null) {
            throw PartitionError::NoPartitionForValue->error($partitioning->method === PartitionMethod::Range || $partitioning->method === PartitionMethod::List ? ($value === null ? 'NULL' : (string) $value) : 'from column_list');
        }

        return $found;
    }

    /**
     * Answers the first RANGE partition whose bound is above a value or a row of values, or null.
     *
     * @param list<int|float|string|null>|null $values
     */
    public function range(?array $values, bool $null): ?int
    {
        if ($null || $values === null) {
            return 0;
        }
        foreach ($this->partitioning->partitions as $index => $partition) {
            if ($partition->bound === null || $this->below($values, $partition->bound)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Tells whether values are below a bound, compared item by item: NULL below anything, MAXVALUE above anything.
     *
     * @param list<int|float|string|null> $values
     * @param list<int|float|string|null> $bound
     */
    public function below(array $values, array $bound): bool
    {
        foreach ($bound as $index => $limit) {
            $value = $values[$index] ?? null;
            if ($limit === null) {
                return true;
            }
            if ($value === null) {
                return true;
            }
            $position = $this->partitioning->columns[$index] ?? null;
            $order = $position === null ? Decimal::compare((string) $value, (string) $limit) : Order::compare($value, $limit, $this->definition->columns[$position]->domain);
            if ($order !== 0) {
                return $order < 0;
            }
        }

        return false;
    }

    /**
     * Answers the LIST partition that lists values, or null.
     *
     * @param list<int|float|string|null> $values
     */
    public function listed(array $values): ?int
    {
        foreach ($this->partitioning->partitions as $index => $partition) {
            foreach ($partition->values as $listed) {
                if ($this->equal($values, $listed)) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * Tells whether two rows of values are equal, NULL equal to NULL.
     *
     * @param list<int|float|string|null> $left
     * @param list<int|float|string|null> $right
     */
    public function equal(array $left, array $right): bool
    {
        foreach ($left as $index => $value) {
            $other = $right[$index] ?? null;
            if ($value === null || $other === null) {
                if ($value !== $other) {
                    return false;
                }
                continue;
            }
            $position = $this->partitioning->columns[$index] ?? null;
            if (($position === null ? Decimal::compare((string) $value, (string) $other) : Order::compare($value, $other, $this->definition->columns[$position]->domain)) !== 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Answers the HASH partition of a value.
     */
    public function hash(int|float|string|null $value): int
    {
        $count = count($this->partitioning->partitions);
        $number = $value === null ? '9223372036854775808' : ltrim((string) $value, '-');
        if (!$this->partitioning->linear) {
            return (int) bcmod($number, (string) $count);
        }
        $mask = 1;
        while ($mask < $count) {
            $mask *= 2;
        }
        $low = (int) bcmod($number, (string) $mask);
        while ($low >= $count) {
            $mask = intdiv($mask, 2);
            $low %= $mask;
        }

        return $low;
    }
}
