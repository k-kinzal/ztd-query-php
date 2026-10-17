<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Partition;

use MySqlMemory\Command\Definition\Expression\ItemText;
use MySqlMemory\Dictionary\KeyKind;
use MySqlMemory\Dictionary\Partition\Partitioning;
use MySqlMemory\Dictionary\Partition\PartitionMethod;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\Family\PartitionError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnUse;
use SqlSemantics\Platform\MySql\Statement\Partition\Bound\LessThan;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ColumnsMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\ExpressionMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\HashMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\KeyMethod;
use SqlSemantics\Platform\MySql\Statement\Partition\Method\PartitionKind;
use SqlSemantics\Platform\MySql\Statement\Partition\PartitionClause;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

/**
 * Builds how a table a CREATE TABLE or ALTER TABLE statement partitions splits its rows, refusing what the server refuses.
 *
 * A temporary table, a table of another engine than InnoDB and a table with foreign keys cannot
 * be partitioned. RANGE and LIST partitioning define each partition, by a strictly increasing
 * bound or by a list of values that no other partition repeats, over an integer expression or,
 * with COLUMNS, over columns; HASH partitions by an integer expression and KEY by columns, the
 * primary key when none is named, into a number of partitions named p0, p1 and so on. Every
 * unique key must hold every column the partitioning reads (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/partitioning-types.html,
 * https://dev.mysql.com/doc/refman/8.4/en/partitioning-limitations.html.
 *
 * @visibility MySqlMemory
 */
final class PartitionDefinitions
{
    /**
     * The most partitions a table can have.
     */
    public const LIMIT = 8192;

    /**
     * @param Planner $planner The planner of the statement, which compiles the expressions
     * @param Scope $scope The scope a row of the table is read in
     */
    public function __construct(public readonly Planner $planner, public readonly Scope $scope)
    {
    }

    /**
     * Answers the partitioning a clause declares for a table.
     *
     * @throws SqlError When the partitioning is refused
     */
    public function partitioning(PartitionClause $clause, TableDefinition $definition): Partitioning
    {
        if ($definition->temporary) {
            throw PartitionError::TemporaryPartitioned->error();
        }
        if (strcasecmp($definition->engine, 'InnoDB') !== 0) {
            throw SchemaError::EngineUnsupportedOperation->error('native partitioning');
        }
        if ($definition->foreignKeys !== []) {
            throw PartitionError::ForeignKeys->error();
        }
        $method = $this->method($clause);
        $names = $this->names($clause, $method);
        [$expression, $columns, $read] = $this->source($clause, $definition);
        $bounds = new PartitionBounds($this->planner, $method, $columns, $definition);
        $partitions = $bounds->partitions($clause, $names);
        $this->unique($definition, $read);
        $text = (new PartitionText())->text($clause, $method, $definition, $partitions, $expression === null ? '' : $this->text($expression, $definition), $columns);

        return new Partitioning($method, $expression === null ? null : $this->planner->compiler->compile($expression, $this->scope), $columns, $partitions, $clause->method instanceof HashMethod ? $clause->method->linear : ($clause->method instanceof KeyMethod && $clause->method->linear), $text, $read);
    }

    /**
     * Answers the method of a clause.
     */
    public function method(PartitionClause $clause): PartitionMethod
    {
        $method = $clause->method;

        return match (true) {
            $method instanceof ExpressionMethod => $method->kind === PartitionKind::Range ? PartitionMethod::Range : PartitionMethod::List,
            $method instanceof ColumnsMethod => $method->kind === PartitionKind::Range ? PartitionMethod::RangeColumns : PartitionMethod::ListColumns,
            $method instanceof HashMethod => PartitionMethod::Hash,
            default => PartitionMethod::Key,
        };
    }

    /**
     * Answers the names of the partitions: those defined, or p0, p1 and so on for the number the clause asks for.
     *
     * @return list<string>
     *
     * @throws SqlError When RANGE or LIST defines no partition, the number is not allowed, or a name repeats
     */
    public function names(PartitionClause $clause, PartitionMethod $method): array
    {
        $ranged = in_array($method, [PartitionMethod::Range, PartitionMethod::RangeColumns, PartitionMethod::List, PartitionMethod::ListColumns], true);
        if ($ranged && $clause->definitions === []) {
            throw PartitionError::PartitionsNotDefined->error($method === PartitionMethod::Range || $method === PartitionMethod::RangeColumns ? 'RANGE' : 'LIST');
        }
        $count = $clause->definitions === [] ? (int) ($clause->partitions->text ?? '1') : count($clause->definitions);
        if ($count === 0) {
            throw PartitionError::NoPartitions->error();
        }
        if ($count > self::LIMIT) {
            throw PartitionError::TooManyPartitions->error();
        }
        if ($clause->definitions === []) {
            return array_map(static fn (int $index): string => 'p' . $index, range(0, $count - 1));
        }
        $names = [];
        foreach ($clause->definitions as $partition) {
            $bound = $partition->values;
            if (!$ranged && $bound !== null) {
                throw $bound instanceof LessThan ? PartitionError::WrongValuesKind->error('RANGE', 'LESS THAN') : PartitionError::WrongValuesKind->error('LIST', 'IN');
            }
            if (isset($names[mb_strtolower($partition->name->value)])) {
                throw PartitionError::DuplicatePartition->error($partition->name->value);
            }
            $names[mb_strtolower($partition->name->value)] = $partition->name->value;
        }

        return array_values($names);
    }

    /**
     * Answers what the clause partitions by: the expression, the positions of the columns, and the positions of every column it reads.
     *
     * @return array{Scalar|null, list<int>, list<int>}
     *
     * @throws SqlError When a column is unknown or of a type the method refuses, or the expression is not an integer
     */
    public function source(PartitionClause $clause, TableDefinition $definition): array
    {
        $method = $clause->method;
        if ($method instanceof ExpressionMethod || $method instanceof HashMethod) {
            $expression = $method->expression;
            $read = [];
            foreach ((new Walker())->find($expression, ColumnUse::class) as $use) {
                $position = $definition->position($use->name->value) ?? throw SchemaError::FieldNotFoundInPartitionFunction->error();
                $read[] = $position;
                if ($expression instanceof ColumnUse && $definition->columns[$position]->domain->kind !== Kind::Integer) {
                    throw PartitionError::FieldTypeNotAllowed->error($definition->columns[$position]->name);
                }
            }
            if ($this->planner->compiler->compile($expression, $this->scope)->domain()->kind !== Kind::Integer) {
                throw PartitionError::FunctionNotAllowed->error();
            }

            return [$expression, [], array_values(array_unique($read))];
        }
        $names = $method instanceof ColumnsMethod || $method instanceof KeyMethod ? $method->columns : [];
        $columns = [];
        foreach ($names as $name) {
            $columns[] = $definition->position($name->value) ?? throw SchemaError::FieldNotFoundInPartitionFunction->error();
        }
        if ($method instanceof KeyMethod && $columns === []) {
            $columns = $definition->primaryKey()->columns ?? throw SchemaError::FieldNotFoundInPartitionFunction->error();
        }
        foreach ($method instanceof ColumnsMethod ? $columns : [] as $position) {
            $kind = $definition->columns[$position]->domain->kind;
            if (!in_array($kind, [Kind::Integer, Kind::String, Kind::Date, Kind::DateTime, Kind::Time], true) || $definition->columns[$position]->domain->field->blob()) {
                throw PartitionError::FieldTypeNotAllowed->error($definition->columns[$position]->name);
            }
        }

        return [null, $columns, $columns];
    }

    /**
     * Refuses a primary or unique key that lacks a column the partitioning reads.
     *
     * @param list<int> $read
     *
     * @throws SqlError When a key lacks one
     */
    public function unique(TableDefinition $definition, array $read): void
    {
        foreach ($definition->keys as $key) {
            if (!$key->unique()) {
                continue;
            }
            $whole = array_values(array_filter($key->columns, static fn (int $position, int $index): bool => ($key->prefixes[$index] ?? null) === null, ARRAY_FILTER_USE_BOTH));
            if (array_diff($read, $whole) !== []) {
                throw PartitionError::UniqueKeyColumns->error($key->kind === KeyKind::Primary ? 'PRIMARY KEY' : 'UNIQUE INDEX');
            }
        }
    }

    /**
     * Writes the expression a table is partitioned by: as the server stores it, and in MySQL 5.6 and 5.7 as written (verified on live 5.6.51 and 5.7.44 servers).
     */
    public function text(Scalar $expression, TableDefinition $definition): string
    {
        $names = [];
        foreach ($definition->columns as $column) {
            $names[mb_strtolower($column->name)] = $column->name;
        }

        $text = new ItemText($names, $this->planner->settings->connectionCollation->charset->name, $this->planner->settings->release());

        return $this->planner->settings->legacy() ? $text->rendered($expression) : $text->text($expression);
    }
}
