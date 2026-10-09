<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Write;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\References;
use MySqlMemory\Storage\Store;
use MySqlMemory\Storage\TimestampZones;
use MySqlMemory\Storage\Writer;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Order;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\RowAlias;

/**
 * Writes the rows of one INSERT or REPLACE statement, and counts what it did.
 *
 * @visibility MySqlMemory
 */
final class Rows
{
    /**
     * The rows the statement affected, as the server counts them.
     */
    public int $affected = 0;

    /**
     * The rows that conflicted with a unique key.
     */
    public int $duplicates = 0;

    /**
     * The first AUTO_INCREMENT value the statement generated, if any.
     */
    public ?int $generated = null;

    /**
     * The scope that places the relations of the FROM clause of the query of INSERT ... SELECT, which ON DUPLICATE KEY UPDATE reads after the row that was to be inserted; null when it reads none.
     */
    public ?Scope $sources = null;

    /**
     * @var list<int|float|string|null> The row of the FROM clause of the query the row being written comes from
     */
    public array $source = [];

    private Writer $writer;

    private ?Scope $updateScope = null;

    /**
     * @var array<int, true> The positions of the columns without a default the statement has warned about
     */
    private array $undefaulted = [];

    /**
     * @param StoredTable $table The table written
     * @param Context $context The statement
     * @param Planner $planner The planner of the statement
     * @param InsertInto $into The head of the statement
     * @param list<Assignment> $onDuplicate The ON DUPLICATE KEY UPDATE assignments
     * @param Session $session The session
     * @param RowAlias|null $alias The alias INSERT ... AS gives the row it was to write, which ON DUPLICATE KEY UPDATE reads after the existing row
     */
    public function __construct(
        public readonly StoredTable $table,
        public readonly Context $context,
        public readonly Planner $planner,
        public readonly InsertInto $into,
        public readonly array $onDuplicate,
        public readonly Session $session,
        public readonly ?RowAlias $alias = null,
    ) {
        $this->writer = new Writer($table, $context);
    }

    /**
     * Writes one row from the values named for some of its columns.
     *
     * A value an INSERT ... SELECT cannot store fails the statement, and the server then also
     * reports ER_NO_DEFAULT_FOR_FIELD for each NOT NULL column without a default that the row
     * had not received a value for yet, in the order of the table.
     *
     * @param list<int> $positions
     * @param list<Evaluable|DefaultRequest|array{int|float|string|null, Domain}> $values
     * @param bool $queried Whether the row is a row of the query of INSERT ... SELECT
     *
     * @throws SqlError When the row is refused
     */
    public function write(array $positions, array $values, int $number, bool $single, bool $queried = false): void
    {
        $definition = $this->table->definition;
        if (count($values) !== count($positions)) {
            throw $single ? QueryError::WrongValueCount->error() : QueryError::WrongValueCountOnRow->error($number);
        }
        $frame = new Frame($this->context);
        $store = new Store($this->context, $number, $this->into->table->name->name->value);
        $row = array_map(static fn ($column) => $column->default->expression === null ? $column->default->value : null, $definition->columns);
        $named = [];
        foreach ($positions as $index => $position) {
            $named[$position] = true;
            $value = $values[$index];
            $column = $definition->columns[$position];
            if ($value instanceof DefaultRequest) {
                $frame->row = $row;
                array_splice($row, $position, 1, [$column->generated !== null ? null : $this->defaulted($position, $frame, $store, $number, true)]);
                $frame->row = [];
                continue;
            }
            [$raw, $domain] = $value instanceof Evaluable ? [$value->evaluate($frame), $value->domain()] : $value;
            try {
                array_splice($row, $position, 1, [$this->notNull($store->value($raw, $domain, $column), $position, $store, $number, $single)]);
            } catch (SqlError $error) {
                throw $queried ? $this->unfilled($error, $named) : $error;
            }
        }
        $row = $this->completed($row, $named, $store, $number, $single);
        $row = $this->triggers()->before('INSERT', $row, null, $this->context) ?? $row;
        [$row, $generated] = $this->writer->autoIncrement($row, $this->context->modes->has('NO_AUTO_VALUE_ON_ZERO'));
        if ($generated !== null) {
            $this->generated ??= $generated;
        }
        $check = $this->writer->violated($row);
        if ($check !== null) {
            if (!$this->into->ignore) {
                throw DataError::CheckConstraintViolated->error($check->name);
            }
            $this->context->diagnostics->warning(DataError::CheckConstraintViolated, DataError::CheckConstraintViolated->message($check->name));

            return;
        }
        if (!$this->partitioned($row)) {
            return;
        }
        $this->place($row, $number, $single);
    }

    /**
     * Tells whether a row of a partitioned table lands in a partition, and in one the statement names; a row that does not is refused, or with IGNORE left out with a warning.
     *
     * @param list<int|float|string|null> $row
     *
     * @throws SqlError When the row is refused without IGNORE
     */
    public function partitioned(array $row): bool
    {
        $definition = $this->table->definition;
        if ($definition->partitioning === null) {
            return true;
        }
        $partitions = new \MySqlMemory\Storage\Partitions($definition, $definition->partitioning, $this->context);
        try {
            $partitions->place($row, $this->into->table->partitions === [] ? null : $partitions->selected($this->into->table->partitions));
        } catch (SqlError $error) {
            if (!$this->into->ignore || !$error->error instanceof \MySqlMemory\Error\Family\PartitionError) {
                throw $error;
            }
            $this->context->diagnostics->warning($error->error, $error->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Fills the columns a row names no value for, in column order: a generated column computed from the values before it, another column its default, which an expression computes from the row so far.
     *
     * Source: https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html.
     *
     * @param list<int|float|string|null> $row
     * @param array<int, true> $named The positions of the columns the row names
     * @return list<int|float|string|null>
     *
     * @throws SqlError When a value is refused
     */
    public function completed(array $row, array $named, Store $store, int $number, bool $single): array
    {
        $frame = new Frame($this->context);
        foreach ($this->table->definition->columns as $position => $column) {
            $frame->row = $row;
            if ($column->generated !== null) {
                $value = $store->value($column->generated->evaluate($frame), $column->generated->domain(), $column);
                array_splice($row, $position, 1, [$this->notNull($value, $position, $store, $number, $single)]);
            } elseif (!isset($named[$position])) {
                array_splice($row, $position, 1, [$this->defaulted($position, $frame, $store, $number, false)]);
            }
        }

        return $row;
    }

    /**
     * Answers an error of INSERT ... SELECT followed by ER_NO_DEFAULT_FOR_FIELD for each NOT NULL column without a default that the row has no value for yet.
     *
     * @param array<int, true> $named The positions of the columns the row has reached
     */
    public function unfilled(SqlError $error, array $named): SqlError
    {
        $following = $error->following;
        foreach ($this->table->definition->columns as $position => $column) {
            if (!isset($named[$position]) && !$column->default->declared && !$column->nullable() && !$column->autoIncrement) {
                $following[] = [DataError::NoDefaultForField->value, DataError::NoDefaultForField->message($column->name)];
            }
        }

        return new SqlError($error->error, $error->getMessage(), $error->getPrevious(), $following);
    }

    /**
     * Answers the value a column takes when a row names none or DEFAULT.
     *
     * A column without a default, as after ALTER COLUMN ... DROP DEFAULT, is ER_NO_DEFAULT_FOR_FIELD
     * under a strict mode; otherwise it takes NULL when it admits NULL, else the implicit default
     * of its type, with the warning once for a column no row names and for each DEFAULT written
     * (verified on a live 8.4 server).
     */
    public function defaulted(int $position, Frame $frame, Store $store, int $number, bool $explicit): int|float|string|null
    {
        $column = $this->table->definition->columns[$position];
        [$has, $value] = $this->writer->default($column, $frame);
        if ($has || $column->autoIncrement) {
            return $value;
        }
        if ($explicit || !isset($this->undefaulted[$position])) {
            $store->adjust(DataError::NoDefaultForField, $column->name);
        }
        $this->undefaulted[$position] = true;

        return $column->nullable() ? null : $this->writer->implicit($column);
    }

    /**
     * Refuses NULL for a NOT NULL column, or replaces it by the implicit default with a warning.
     *
     * A strict mode refuses it, and so does a single-row INSERT without IGNORE; IGNORE, and a
     * statement of several rows or a query under a non-strict mode, store the implicit default.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/constraint-invalid-data.html.
     *
     * @param bool $single Whether the statement writes a single row of values
     *
     * @throws SqlError When NULL is refused
     */
    public function notNull(int|float|string|null $value, int $position, Store $store, int $number, bool $single): int|float|string|null
    {
        $column = $this->table->definition->columns[$position];
        if ($value !== null || $column->nullable() || $column->autoIncrement) {
            return $value;
        }
        if (($single && !$this->into->ignore) || $this->context->strict) {
            throw DataError::BadNull->error($column->name);
        }
        $this->context->warning(DataError::BadNull, $column->name);

        return $this->writer->implicit($column);
    }

    /**
     * Places a complete row: inserts it, or resolves its conflict with a unique key.
     *
     * @param list<int|float|string|null> $row
     * @param bool $single Whether the statement writes a single row of values
     *
     * @throws SqlError When the row conflicts and the statement does not resolve conflicts
     */
    public function place(array $row, int $number, bool $single = false): void
    {
        $data = $this->table->data;
        while (($conflict = $this->writer->conflict($row, null, $this->into->replace || $this->onDuplicate !== [] ? \MySqlMemory\Concurrency\LockMode::Exclusive : \MySqlMemory\Concurrency\LockMode::Shared)) !== null) {
            [$existing, $key] = $conflict;
            $this->duplicates++;
            if ($this->into->replace) {
                [$affected, $placed] = (new Replacement($this->table, $this->context, $this->session))->replace($row, $existing, $key);
                $this->affected += $affected;
                if ($placed) {
                    return;
                }
                continue;
            }
            if ($this->onDuplicate !== []) {
                $this->update($existing, $row, $number, $single);

                return;
            }
            if ($this->into->ignore) {
                $this->writer->ignored($row, $key);

                return;
            }
            throw $this->writer->duplicate($row, $key);
        }
        $orphan = $this->references()->orphan($this->table, $row);
        if ($orphan !== null) {
            $error = $this->references()->violation($this->table, $orphan, false);
            if (!$this->into->ignore) {
                throw $error;
            }
            $this->context->diagnostics->warning($error->error, $error->getMessage());

            return;
        }
        $this->session->transaction->write($this->table, $data->nextRow);
        $data->insert($row);
        $this->affected++;
        $this->triggers()->after('INSERT', $row, null, $this->context);
    }

    /**
     * Answers the triggers of the table.
     */
    public function triggers(): \MySqlMemory\Program\Triggers
    {
        return new \MySqlMemory\Program\Triggers($this->session, $this->table);
    }

    /**
     * Answers the keeper of the foreign keys the statement writes.
     */
    public function references(): References
    {
        return new References($this->session, $this->context);
    }

    /**
     * Applies ON DUPLICATE KEY UPDATE to the row a new row conflicts with.
     *
     * An assignment of NULL to a NOT NULL column is refused or stored as the implicit default
     * as in the row inserted.
     *
     * @param list<int|float|string|null> $new
     * @param bool $single Whether the statement writes a single row of values
     */
    public function update(int $existing, array $new, int $number, bool $single = false): void
    {
        $data = $this->table->data;
        $old = $data->rows[$existing];
        $scope = $this->updateScope();
        $zones = new TimestampZones();
        $frame = new Frame($this->context, [...$zones->local($this->table, $old, $this->context), ...$zones->local($this->table, $new, $this->context), ...$this->source]);
        $row = $old;
        $store = new Store($this->context, $number, $this->into->table->name->name->value);
        $assigned = [];
        foreach ($this->onDuplicate as $assignment) {
            $position = (new Assignments($this->planner, $this->table))->position($assignment->column);
            $assigned[$position] = true;
            $value = $this->planner->compiler->compile($assignment->value, $scope);
            $stored = $this->notNull($store->value($value->evaluate($frame), $value->domain(), $this->table->definition->columns[$position]), $position, $store, $number, $single);
            array_splice($row, $position, 1, [$stored]);
            $frame->row = [...$zones->local($this->table, $row, $this->context), ...array_slice($frame->row, count($row))];
        }
        $row = $this->writer->generate($row, $store, fn (int $position, $value) => $this->notNull($value, $position, $store, $number, $single));
        $row = $this->triggers()->before('UPDATE', $row, $old, $this->context) ?? $row;
        $changed = false;
        foreach ($row as $position => $value) {
            $changed = $changed || Order::key($value, $this->table->definition->columns[$position]->domain) !== Order::key($old[$position], $this->table->definition->columns[$position]->domain);
        }
        if (!$changed) {
            $this->triggers()->after('UPDATE', $row, $old, $this->context);

            return;
        }
        $row = $this->writer->refresh($row, $assigned);
        $check = $this->writer->violated($row);
        if ($check !== null) {
            throw DataError::CheckConstraintViolated->error($check->name);
        }
        $conflict = $this->writer->conflict($row, $existing);
        if ($conflict !== null) {
            throw $this->writer->duplicate($row, $conflict[1]);
        }
        $orphan = $this->references()->orphan($this->table, $row, $old);
        if ($orphan !== null) {
            throw $this->references()->violation($this->table, $orphan, false);
        }
        $this->references()->updating($this->table, $old, $row);
        $this->session->transaction->write($this->table, $existing);
        $data->update($existing, $row);
        $this->affected += 2;
        $this->triggers()->after('UPDATE', $row, $old, $this->context);
    }

    /**
     * Answers the scope ON DUPLICATE KEY UPDATE compiles in: the existing row, then the row that was to be inserted, then the row of the FROM clause of the query of INSERT ... SELECT.
     */
    public function updateScope(): Scope
    {
        if ($this->updateScope === null) {
            $scope = new Scope();
            $definition = $this->table->definition;
            $domains = array_map(static fn ($column) => $column->domain, $definition->columns);
            $names = array_map(static fn ($column): string => $column->name, $definition->columns);
            $scope->place($this->into->table, $domains, $names, $definition);
            if ($this->alias !== null) {
                $scope->place($this->alias, $domains, $names, $definition);
            } elseif ($this->sources !== null) {
                $scope->place($this->into, $domains, $names);
            }
            foreach ($this->sources === null ? [] : $this->sources->nodes as $node) {
                $id = spl_object_id($node);
                if (isset($this->sources->offsets[$id]) && !isset($scope->offsets[$id])) {
                    $scope->place($node, $this->sources->columns[$id], $this->sources->names[$id] ?? [], $this->sources->tables[$id] ?? null);
                }
            }
            $scope->inserted = $definition;
            $this->updateScope = $scope;
        }

        return $this->updateScope;
    }
}
