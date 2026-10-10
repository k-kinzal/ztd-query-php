<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Partition;

use MySqlMemory\Dictionary\Partition\Partition;
use MySqlMemory\Dictionary\Partition\PartitionMethod;
use MySqlMemory\Dictionary\TableDefinition;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionOptionKind;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;

/**
 * Writes how a table is partitioned as SHOW CREATE TABLE writes it, in a versioned comment after the table options.
 *
 * The method and what it partitions by come first: an expression as the server stores it, the
 * COLUMNS or KEY columns as written. RANGE and LIST then list each partition with its bound or
 * values as evaluated, NULL first in a list, and its engine; HASH and KEY name the number of
 * partitions when the statement does, or list the partitions it defines (verified on a live 8.4
 * server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-table.html.
 *
 * @visibility MySqlMemory
 */
final class PartitionText
{
    /**
     * Writes the partitioning of a table.
     *
     * @param list<Partition> $partitions
     * @param list<int> $columns The positions of the columns COLUMNS partitioning reads
     */
    public function text(PartitionClause $clause, PartitionMethod $method, TableDefinition $definition, array $partitions, string $expression, array $columns = []): string
    {
        $source = $clause->method;
        $kinds = array_map(static fn (int $position): Kind => $definition->columns[$position]->domain->kind, $columns);
        $columns = $source instanceof ColumnsMethod || $source instanceof KeyMethod ? implode(',', array_map(static fn ($name): string => $name->value, $source->columns)) : '';
        $head = match ($method) {
            PartitionMethod::Range, PartitionMethod::List => '/*!50100 PARTITION BY ' . ($method === PartitionMethod::Range ? 'RANGE' : 'LIST') . ' (' . $expression . ')',
            PartitionMethod::RangeColumns, PartitionMethod::ListColumns => '/*!50500 PARTITION BY ' . ($method === PartitionMethod::RangeColumns ? 'RANGE' : 'LIST') . '  COLUMNS(' . $columns . ')',
            PartitionMethod::Hash => '/*!50100 PARTITION BY ' . ($source instanceof HashMethod && $source->linear ? 'LINEAR ' : '') . 'HASH (' . $expression . ')',
            PartitionMethod::Key => $this->key($source instanceof KeyMethod ? $source : null, $columns),
        };
        if ($clause->definitions === []) {
            return $head . ($clause->partitions === null ? '' : "\nPARTITIONS " . $clause->partitions->text) . ' */';
        }
        $lines = [];
        foreach ($clause->definitions as $index => $written) {
            $line = 'PARTITION ' . $written->name->value . $this->values($method, $partitions[$index], $kinds);
            foreach ($written->options as $option) {
                if ($option->kind === PartitionOptionKind::Comment && $option->text !== null) {
                    $line .= " COMMENT = '" . str_replace("'", "''", $option->text->value) . "'";
                }
            }
            $lines[] = $line . ' ENGINE = InnoDB';
        }

        return $head . "\n(" . implode(",\n ", $lines) . ') */';
    }

    /**
     * Writes the head of KEY partitioning, with the algorithm when one is written.
     */
    public function key(?KeyMethod $method, string $columns): string
    {
        $linear = $method !== null && $method->linear ? 'LINEAR ' : '';
        if ($method?->algorithm !== null) {
            return '/*!50100 PARTITION BY ' . $linear . 'KEY */ /*!50611 ALGORITHM = ' . $method->algorithm->text . ' */ /*!50100 (' . $columns . ')';
        }

        return '/*!50100 PARTITION BY ' . $linear . 'KEY (' . $columns . ')';
    }

    /**
     * Writes the bound or the values of a partition.
     *
     * @param list<Kind> $kinds The kind of each column COLUMNS partitioning reads
     */
    public function values(PartitionMethod $method, Partition $partition, array $kinds): string
    {
        if ($method === PartitionMethod::Range) {
            return ' VALUES LESS THAN ' . ($partition->bound === null ? 'MAXVALUE' : '(' . $this->value($partition->bound[0] ?? null, null) . ')');
        }
        if ($method === PartitionMethod::RangeColumns) {
            return ' VALUES LESS THAN (' . implode(',', array_map(fn ($value, int $index): string => $value === null ? 'MAXVALUE' : $this->value($value, $kinds[$index] ?? null), $partition->bound ?? [], array_keys($partition->bound ?? []))) . ')';
        }
        if ($method === PartitionMethod::List) {
            $values = array_map(static fn (array $row) => $row[0], $partition->values);
            usort($values, static fn ($left, $right): int => ($right === null) <=> ($left === null));

            return ' VALUES IN (' . implode(',', array_map(fn ($value): string => $this->value($value, null), $values)) . ')';
        }
        if ($method === PartitionMethod::ListColumns) {
            $rows = array_map(fn (array $row): string => implode(',', array_map(fn ($value, int $index): string => $this->value($value, $kinds[$index] ?? null), $row, array_keys($row))), $partition->values);
            $single = $rows !== [] && count($partition->values[0]) === 1;

            return ' VALUES IN (' . implode(',', array_map(static fn (string $row): string => $single ? $row : '(' . $row . ')', $rows)) . ')';
        }

        return '';
    }

    /**
     * Writes a value of a bound: NULL, a number, or a quoted string or temporal value.
     */
    public function value(int|float|string|null $value, ?Kind $kind): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return in_array($kind, [Kind::String, Kind::Date, Kind::DateTime, Kind::Time], true) ? "'" . str_replace("'", "''", (string) $value) . "'" : (string) $value;
    }
}
