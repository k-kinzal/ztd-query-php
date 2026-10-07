<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Write;

use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
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
     * @param list<int> $positions
     * @param list<Evaluable|DefaultRequest|array{int|float|string|null, Domain}> $values
     *
     * @throws \MySqlMemory\Error\SqlError When the row is refused
     */
    public function write(array $positions, array $values, int $number, bool $single): void
    {
        $definition = $this->table->definition;
        if (count($values) !== count($positions)) {
            throw $single ? ErrorCode::WrongValueCount->error() : ErrorCode::WrongValueCountOnRow->error($number);
        }
        $frame = new Frame($this->context);
        $store = new Store($this->context, $number);
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
            array_splice($row, $position, 1, [$this->notNull($store->value($raw, $domain, $column), $position, $store, $number, $single)]);
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
        $this->place($row, $number);
    }

    /**
     * Answers the value a column takes when a row names none or DEFAULT.
     */
    public function defaulted(int $position, Frame $frame, Store $store, int $number, bool $explicit): int|float|string|null
    {
        $column = $this->table->definition->columns[$position];
        [$has, $value] = $this->writer->default($column, $frame);
        if ($has || $column->autoIncrement) {
            return $value;
        }
        $store->adjust(ErrorCode::NoDefaultForField, $column->name);

        return $this->writer->implicit($column);
    }

    /**
     * Refuses NULL for a NOT NULL column, or replaces it by the implicit default.
     */
    public function notNull(int|float|string|null $value, int $position, Store $store, int $number, bool $single): int|float|string|null
    {
        $column = $this->table->definition->columns[$position];
        if ($value !== null || $column->nullable() || $column->autoIncrement) {
            return $value;
        }
        if ($single || $this->context->strict) {
            throw ErrorCode::BadNull->error($column->name);
        }
        $this->context->warning(ErrorCode::BadNull, $column->name);

        return $this->writer->implicit($column);
    }

    /**
     * Places a complete row: inserts it, or resolves its conflict with a unique key.
     *
     * @param list<int|float|string|null> $row
     *
     * @throws \MySqlMemory\Error\SqlError When the row conflicts and the statement does not resolve conflicts
     */
    public function place(array $row, int $number): void
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
                $this->update($existing, $row, $number);

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
     * @param list<int|float|string|null> $new
     */
    public function update(int $existing, array $new, int $number): void
    {
        $data = $this->table->data;
        $old = $data->rows[$existing];
        $scope = $this->updateScope();
        $frame = new Frame($this->context, [...$old, ...$new]);
        $row = $old;
        $store = new Store($this->context, $number);
        foreach ($this->onDuplicate as $assignment) {
            $position = (new Assignments($this->planner, $this->table))->position($assignment->column);
            $value = $this->planner->compiler->compile($assignment->value, $scope);
            $stored = $store->value($value->evaluate($frame), $value->domain(), $this->table->definition->columns[$position]);
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
