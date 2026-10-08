<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Write;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Store;
use MySqlMemory\Storage\Writer;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Value\Order;
use SqlSemantics\Platform\MySql\Statement\Dml\Assignment;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;

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

    private Writer $writer;

    private ?Scope $updateScope = null;

    /**
     * @param StoredTable $table The table written
     * @param Context $context The statement
     * @param Planner $planner The planner of the statement
     * @param InsertInto $into The head of the statement
     * @param list<Assignment> $onDuplicate The ON DUPLICATE KEY UPDATE assignments
     * @param Session $session The session
     */
    public function __construct(
        public readonly StoredTable $table,
        public readonly Context $context,
        public readonly Planner $planner,
        public readonly InsertInto $into,
        public readonly array $onDuplicate,
        public readonly Session $session,
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
            throw $single ? ErrorCode::WrongValueCount->error() : ErrorCode::WrongValueCountOnRow->error($number);
        }
        $frame = new Frame($this->context);
        $store = new Store($this->context, $number, $this->into->table->name->name->value);
        $row = array_fill(0, count($definition->columns), null);
        $named = [];
        foreach ($positions as $index => $position) {
            $named[$position] = true;
            $value = $values[$index];
            $column = $definition->columns[$position];
            if ($value instanceof DefaultRequest) {
                array_splice($row, $position, 1, [$this->defaulted($position, $frame, $store, $number, true)]);
                continue;
            }
            [$raw, $domain] = $value instanceof Evaluable ? [$value->evaluate($frame), $value->domain()] : $value;
            try {
                array_splice($row, $position, 1, [$this->notNull($store->value($raw, $domain, $column), $position, $store, $number, $single)]);
            } catch (SqlError $error) {
                throw $queried ? $this->unfilled($error, $named) : $error;
            }
        }
        foreach ($definition->columns as $position => $column) {
            if (!isset($named[$position])) {
                array_splice($row, $position, 1, [$this->defaulted($position, $frame, $store, $number, false)]);
            }
        }
        [$row, $generated] = $this->writer->autoIncrement($row, $this->context->modes->has('NO_AUTO_VALUE_ON_ZERO'));
        if ($generated !== null) {
            $this->generated ??= $generated;
        }
        $this->place($row, $number, $single);
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
                $following[] = [ErrorCode::NoDefaultForField->value, ErrorCode::NoDefaultForField->message($column->name)];
            }
        }

        return new SqlError($error->error, $error->getMessage(), $error->getPrevious(), $following);
    }

    /**
     * Answers the value a column takes when a row names none or DEFAULT.
     *
     * A column without a default, as after ALTER COLUMN ... DROP DEFAULT, is ER_NO_DEFAULT_FOR_FIELD
     * under a strict mode; otherwise it takes NULL when it admits NULL, else the implicit default
     * of its type (verified on a live 8.4 server).
     */
    public function defaulted(int $position, Frame $frame, Store $store, int $number, bool $explicit): int|float|string|null
    {
        $column = $this->table->definition->columns[$position];
        [$has, $value] = $this->writer->default($column, $frame);
        if ($has || $column->autoIncrement) {
            return $value;
        }
        $store->adjust(ErrorCode::NoDefaultForField, $column->name);

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
            throw ErrorCode::BadNull->error($column->name);
        }
        $this->context->warning(ErrorCode::BadNull, $column->name);

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
        while (($conflict = $this->writer->conflict($row)) !== null) {
            [$existing, $key] = $conflict;
            $this->duplicates++;
            if ($this->into->replace) {
                if ($key === $this->lastUnique()) {
                    $same = $data->rows[$existing] === $row;
                    $data->update($existing, $row);
                    $this->affected += $same ? 1 : 2;

                    return;
                }
                $data->delete($existing);
                $this->affected++;
                continue;
            }
            if ($this->onDuplicate !== []) {
                $this->update($existing, $row, $number, $single);

                return;
            }
            if ($this->into->ignore) {
                $this->context->warning(ErrorCode::DuplicateEntry, ...$this->writer->entry($row, $key));

                return;
            }
            throw $this->writer->duplicate($row, $key);
        }
        $data->insert($row);
        $this->affected++;
    }

    /**
     * Answers the last unique key of the table, which REPLACE resolves by updating the row in place.
     */
    public function lastUnique(): ?\MySqlMemory\Dictionary\Key
    {
        $last = null;
        foreach ($this->table->definition->keys as $key) {
            if ($key->unique()) {
                $last = $key;
            }
        }

        return $last;
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
        $frame = new Frame($this->context, [...$old, ...$new]);
        $row = $old;
        $store = new Store($this->context, $number, $this->into->table->name->name->value);
        $assigned = [];
        foreach ($this->onDuplicate as $assignment) {
            $position = (new Assignments($this->planner, $this->table))->position($assignment->column);
            $assigned[$position] = true;
            $value = $this->planner->compiler->compile($assignment->value, $scope);
            $stored = $this->notNull($store->value($value->evaluate($frame), $value->domain(), $this->table->definition->columns[$position]), $position, $store, $number, $single);
            array_splice($row, $position, 1, [$stored]);
            array_splice($frame->row, $position, 1, [$stored]);
        }
        $changed = false;
        foreach ($row as $position => $value) {
            $changed = $changed || Order::key($value, $this->table->definition->columns[$position]->domain) !== Order::key($old[$position], $this->table->definition->columns[$position]->domain);
        }
        if (!$changed) {
            return;
        }
        $row = $this->writer->refresh($row, $assigned);
        $conflict = $this->writer->conflict($row, $existing);
        if ($conflict !== null) {
            throw $this->writer->duplicate($row, $conflict[1]);
        }
        $data->update($existing, $row);
        $this->affected += 2;
    }

    /**
     * Answers the scope ON DUPLICATE KEY UPDATE compiles in: the existing row, then the row that was to be inserted.
     */
    public function updateScope(): Scope
    {
        if ($this->updateScope === null) {
            $scope = new Scope();
            $definition = $this->table->definition;
            $domains = array_map(static fn ($column) => $column->domain, $definition->columns);
            $names = array_map(static fn ($column): string => $column->name, $definition->columns);
            $scope->place($this->into->table, $domains, $names, $definition);
            $scope->inserted = $definition;
            $this->updateScope = $scope;
        }

        return $this->updateScope;
    }
}
