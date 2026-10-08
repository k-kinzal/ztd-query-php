<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Partition;

use MySqlMemory\Dictionary\Partition\Partition;
use MySqlMemory\Dictionary\Partition\PartitionMethod;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\PartitionError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Storage\Store;
use MySqlMemory\Value\Decimal;
use MySqlMemory\Value\Order;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\PartitionRow;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesIn;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\ValuesInRows;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Reads the values that bound the partitions of a RANGE or LIST partitioned table.
 *
 * A RANGE partition is bound by VALUES LESS THAN, strictly above the bound of the partition
 * before it; MAXVALUE bounds only the last one, and NULL none. A LIST partition holds VALUES IN,
 * which no other partition repeats. Without COLUMNS each value is an integer; with COLUMNS a
 * value per column, stored as the column stores it (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-range.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-list.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-columns.html.
 *
 * @visibility MySqlMemory
 */
final class PartitionBounds
{
    /**
     * @param Planner $planner The planner of the statement, which evaluates the values
     * @param PartitionMethod $method The partitioning method
     * @param list<int> $columns The positions of the columns COLUMNS partitioning reads
     * @param TableDefinition $definition The table partitioned
     */
    public function __construct(public readonly Planner $planner, public readonly PartitionMethod $method, public readonly array $columns, public readonly TableDefinition $definition)
    {
    }

    /**
     * Answers the partitions of a clause with their bounds or values.
     *
     * @param list<string> $names The names of the partitions in order
     * @return list<Partition>
     *
     * @throws SqlError When a bound or value is refused
     */
    public function partitions(PartitionClause $clause, array $names): array
    {
        if ($this->method === PartitionMethod::Hash || $this->method === PartitionMethod::Key) {
            return array_map(static fn (string $name): Partition => new Partition($name), $names);
        }
        $partitions = [];
        $seen = [];
        foreach ($clause->definitions as $index => $definition) {
            $bound = $definition->values;
            $ranged = $this->method === PartitionMethod::Range || $this->method === PartitionMethod::RangeColumns;
            if ($ranged && !$bound instanceof LessThan) {
                throw PartitionError::WrongValuesKind->error('LIST', 'IN');
            }
            if (!$ranged && !$bound instanceof ValuesIn && !$bound instanceof ValuesInRows) {
                throw PartitionError::WrongValuesKind->error('RANGE', 'LESS THAN');
            }
            if ($bound instanceof LessThan) {
                $partitions[] = new Partition($names[$index], $this->bound($bound, $index === count($clause->definitions) - 1, $names[$index], $partitions));
                continue;
            }
            $values = [];
            foreach ($bound instanceof ValuesInRows ? $bound->rows : array_map(static fn ($item): PartitionRow => new PartitionRow([$item]), $bound->row->items) as $row) {
                $value = $this->values($row, $names[$index], false);
                $key = $this->key($value);
                if (isset($seen[$key])) {
                    throw PartitionError::ListConstantTwice->error();
                }
                $seen[$key] = true;
                $values[] = $value;
            }
            $partitions[] = new Partition($names[$index], null, $values);
        }

        return $partitions;
    }

    /**
     * Reads the bound of a RANGE partition: null for MAXVALUE, refusing a bound that is not above the one before.
     *
     * @param list<Partition> $before The partitions before it
     * @return list<int|float|string|null>|null
     *
     * @throws SqlError When the bound is refused
     */
    public function bound(LessThan $bound, bool $last, string $name, array $before): ?array
    {
        if ($bound->row === null) {
            if (!$last) {
                throw PartitionError::MaxValueNotLast->error();
            }

            return null;
        }
        $values = $this->values($bound->row, $name, true);
        $previous = $before === [] ? null : $before[count($before) - 1]->bound;
        if ($previous !== null && $this->compare($previous, $values) >= 0) {
            throw PartitionError::RangeNotIncreasing->error();
        }

        return $values;
    }

    /**
     * Reads the values of a row of a partition definition, a MAXVALUE item read as null.
     *
     * @return list<int|float|string|null>
     *
     * @throws SqlError When a value is refused
     */
    public function values(PartitionRow $row, string $name, bool $bound): array
    {
        $values = [];
        $columns = $this->method === PartitionMethod::RangeColumns || $this->method === PartitionMethod::ListColumns;
        foreach ($row->items as $index => $item) {
            if (!$item instanceof Scalar) {
                $values[] = null;
                continue;
            }
            $evaluable = $this->planner->compiler->compile($item, new Scope());
            $context = $this->planner->compiler->connection->context;
            $value = $evaluable->evaluate(new Frame($context));
            if ($value === null && $bound) {
                throw PartitionError::NullInLessThan->error();
            }
            if (!$columns && $value !== null && $evaluable->domain()->kind !== Kind::Integer) {
                throw PartitionError::ValuesNotInteger->error($name);
            }
            $column = $columns ? $this->definition->columns[$this->columns[$index] ?? 0] : null;
            $values[] = $column === null || $value === null ? $value : (new Store($context))->value($value, $evaluable->domain(), $column);
        }

        return $values;
    }

    /**
     * Compares two bounds of RANGE partitions, a null item being MAXVALUE.
     *
     * @param list<int|float|string|null> $left
     * @param list<int|float|string|null> $right
     */
    public function compare(array $left, array $right): int
    {
        foreach ($left as $index => $value) {
            $other = $right[$index] ?? null;
            if ($value === null || $other === null) {
                $order = ($value === null) <=> ($other === null);
            } else {
                $position = $this->columns[$index] ?? null;
                $order = $position === null ? Decimal::compare((string) $value, (string) $other) : Order::compare($value, $other, $this->definition->columns[$position]->domain);
            }
            if ($order !== 0) {
                return $order;
            }
        }

        return 0;
    }

    /**
     * Answers a key that is equal for two equal rows of LIST values.
     *
     * @param list<int|float|string|null> $values
     */
    public function key(array $values): string
    {
        $key = '';
        foreach ($values as $index => $value) {
            $position = $this->columns[$index] ?? null;
            $key .= ($value === null ? "\1" : ($position === null ? (string) $value : Order::key($value, $this->definition->columns[$position]->domain))) . "\0";
        }

        return $key;
    }
}
