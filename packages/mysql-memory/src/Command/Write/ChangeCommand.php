<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Write;

use MySqlMemory\Command\Command;
use MySqlMemory\Concurrency\LockMode;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Planner;
use MySqlMemory\Program\Triggers;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\ClusterOrder;
use MySqlMemory\Storage\Constrained;
use MySqlMemory\Storage\Partitions;
use MySqlMemory\Storage\References;
use MySqlMemory\Storage\Store;
use MySqlMemory\Storage\TimestampZones;
use MySqlMemory\Storage\Writer;
use MySqlMemory\Value\Order;
use Override;
use SqlSemantics\Platform\MySql\Statement\Dml\DefaultRequest;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\DeleteOption;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\DeclaredTable;

/**
 * Executes single-table UPDATE and DELETE.
 *
 * The rows of the table that meet the WHERE condition are taken in clustered index order, or in
 * the ORDER BY order, up to the LIMIT. An UPDATE evaluates its assignments left to right, each
 * seeing the values the ones before it assigned. It counts changed rows by default and matched
 * rows when the connection requested CLIENT_FOUND_ROWS.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/update.html.
 *
 * @visibility MySqlMemory
 */
final class ChangeCommand implements Command
{
    /**
     * Answers true.
     */
    #[Override]
    public function clearsDiagnostics(): bool
    {
        return true;
    }

    /**
     * Updates or deletes the rows.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof Update || $statement instanceof Delete);
        $relation = $statement instanceof Delete ? $statement->table : ($statement->tables[0] ?? null);
        if ($statement instanceof Update && (count($statement->tables) !== 1 || !$relation instanceof TableReference)) {
            throw StatementError::NotSupportedYet->error('multiple-table UPDATE');
        }
        assert($relation instanceof TableReference || $relation instanceof WriteTarget);
        $table = $this->table($operation, $relation, $session);
        $planner = new Planner($statement, $operation->facts, $session->settings(), $connection, $session->instance->dictionary);
        $context->strict = $context->modes->strict() && !($statement instanceof Update ? $statement->ignore : in_array(DeleteOption::Ignore, $statement->options, true));
        $scope = new Scope();
        $definition = $table->definition;
        $scope->place($relation, array_map(static fn ($column) => $column->domain, $definition->columns), array_map(static fn ($column): string => $column->name, $definition->columns), $definition);
        $partitioning = $definition->partitioning;
        $selected = $partitioning === null || $relation->partitions === [] ? null : (new Partitions($definition, $partitioning, $context))->selected($relation->partitions);
        $matched = $this->matched($statement, $planner, $scope, $table, $context, $selected);
        $session->transaction->touch($table);
        if ($statement instanceof Delete) {
            return new Completion($this->delete($table, $matched, new References($session, $context), in_array(DeleteOption::Ignore, $statement->options, true)), 0, $context->diagnostics->count());
        }
        $changed = $this->update($statement, $planner, $scope, $table, $context, $matched, new References($session, $context), $selected);

        return new Completion($session->variables->clientFoundRows ? count($matched) : $changed, 0, $context->diagnostics->count(), sprintf('Rows matched: %d  Changed: %d  Warnings: %d', count($matched), $changed, $context->diagnostics->count()));
    }

    /**
     * Finds the table a statement changes.
     *
     * @throws \MySqlMemory\Error\SqlError When the table does not exist
     */
    public function table(Operation $operation, TableReference|WriteTarget $relation, Session $session): StoredTable
    {
        $resolution = $operation->facts->relation($relation)->table;
        $schema = $relation->name->schema->value ?? $session->variables->database;
        if ($resolution instanceof DeclaredTable) {
            $schema = $resolution->table->name->schema->value ?? $schema;
        }
        $table = $session->instance->dictionary->table($schema, $relation->name->name->value);
        if ($table === null) {
            throw QueryError::NoSuchTable->error($schema, $relation->name->name->value);
        }
        if ($relation->partitions !== [] && $table->definition->partitioning === null) {
            throw SchemaError::PartitionClauseOnNonpartitioned->error();
        }

        return $table;
    }

    /**
     * Answers the numbers of the rows the statement changes, in the order it changes them: a partitioned table is read partition by partition, only the partitions the statement names.
     *
     * The statement reads the latest rows and locks each row it changes exclusively, as InnoDB
     * does. A row another transaction holds a lock on is waited for when its latest or its
     * committed version meets the WHERE condition; an UPDATE under READ COMMITTED or READ
     * UNCOMMITTED reads such a row semi-consistently and waits only when its committed version
     * meets the condition. Once locked, the latest version of the row is read again. Without
     * ORDER BY the statement stops reading at its LIMIT.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locks-set.html,
     * https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html#isolevel_read-committed.
     *
     * @param list<int>|null $selected The partitions the statement names, or null when it names none
     * @return list<int>
     *
     * @throws \MySqlMemory\Error\SqlError When a row cannot be locked
     */
    public function matched(Update|Delete $statement, Planner $planner, Scope $scope, StoredTable $table, Context $context, ?array $selected = null): array
    {
        $transaction = $context->variables->instance->transactions->of($context->variables->connection);
        $rows = (new ClusterOrder())->rows($table, $transaction?->access->rows($table, LockMode::Exclusive));
        $partitioning = $table->definition->partitioning;
        if ($partitioning !== null && ($selected !== null || $partitioning->method !== \MySqlMemory\Dictionary\Partition\PartitionMethod::Key)) {
            $rows = (new Partitions($table->definition, $partitioning, $context))->ordered($rows, $selected);
        }
        $where = $statement->where === null ? null : $planner->compiler->compile($statement->where, $scope);
        $keys = array_map(static fn ($item): array => [$planner->compiler->compile($item->expression, $scope), $item->direction?->value === 'DESC'], $statement->orderBy);
        $frame = new Frame($context);
        $limit = $planner->blocks->limit(new \MySqlMemory\Plan\Path\Source\SingleRow(), $statement->limit, null);
        $count = $limit instanceof \MySqlMemory\Plan\Path\Transform\Limit ? $limit->count : null;
        $matched = [];
        foreach ($rows as $number => $row) {
            if ($keys === [] && $count !== null && count($matched) >= $count) {
                break;
            }
            $values = $this->selected($table, $row, $frame, $where, $keys);
            if ($transaction !== null && $transaction->access->contended($table, $number, LockMode::Exclusive)) {
                $committed = $transaction->access->committed($table, $number);
                $semiConsistent = $statement instanceof Update && in_array($transaction->isolation, [\MySqlMemory\Concurrency\Isolation::ReadCommitted, \MySqlMemory\Concurrency\Isolation::ReadUncommitted], true);
                if (($semiConsistent || $values === null) && ($committed === null || $this->selected($table, $committed[0], $frame, $where, $keys) === null)) {
                    continue;
                }
                $transaction->access->lock($table, $number, LockMode::Exclusive);
                $values = $this->selected($table, $table->data->rows[$number] ?? null, $frame, $where, $keys);
            }
            if ($values === null) {
                continue;
            }
            $transaction?->access->lock($table, $number, LockMode::Exclusive);
            $matched[] = [$number, $values];
        }
        $numbers = array_map(static fn (array $match): int => $match[0], $this->sorted($matched, $keys));

        return $count !== null ? array_slice($numbers, 0, $count) : $numbers;
    }

    /**
     * Sorts the matched rows by the values of their ORDER BY keys, keeping the order of rows whose keys are equal.
     *
     * @param list<array{int, list<int|float|string|null>}> $matched Each matched row number with the values of its keys
     * @param list<array{\MySqlMemory\Evaluation\Evaluable, bool}> $keys The ORDER BY keys, each with whether it is descending
     * @return list<array{int, list<int|float|string|null>}>
     */
    public function sorted(array $matched, array $keys): array
    {
        usort($matched, static function (array $left, array $right) use ($keys): int {
            foreach ($keys as $index => [$key, $descending]) {
                $order = Order::compare($left[1][$index], $right[1][$index], $key->domain());
                if ($order !== 0) {
                    return $descending ? -$order : $order;
                }
            }

            return 0;
        });

        return $matched;
    }

    /**
     * Answers the values of the ORDER BY keys of a row the WHERE condition selects, or null for a row it does not select or no row.
     *
     * @param list<int|float|string|null>|null $row
     * @param list<array{\MySqlMemory\Evaluation\Evaluable, bool}> $keys The ORDER BY keys, each with whether it is descending
     * @return list<int|float|string|null>|null
     *
     * @throws \MySqlMemory\Error\SqlError When the condition or a key cannot be evaluated
     */
    public function selected(StoredTable $table, ?array $row, Frame $frame, ?\MySqlMemory\Evaluation\Evaluable $where, array $keys): ?array
    {
        if ($row === null) {
            return null;
        }
        $context = $frame->context;
        $frame->row = (new TimestampZones())->local($table, $row, $context);
        if ($where !== null && Convert::toBool($where->evaluate($frame), $where->domain(), $context) !== true) {
            return null;
        }

        return array_map(static fn (array $key) => $key[0]->evaluate($frame), $keys);
    }

    /**
     * Applies the assignments of an UPDATE to the matched rows and answers how many changed.
     *
     * @param list<int> $matched
     * @param list<int>|null $selected The partitions the statement names, which a changed row must stay in
     */
    public function update(Update $statement, Planner $planner, Scope $scope, StoredTable $table, Context $context, array $matched, References $references, ?array $selected = null): int
    {
        $assignments = new Assignments($planner, $table);
        $compiled = [];
        foreach ($statement->assignments as $assignment) {
            $compiled[] = [$assignments->position($assignment->column), $assignment->value instanceof DefaultRequest ? $assignment->value : $planner->compiler->compile($assignment->value, $scope)];
        }
        $writer = new Writer($table, $context);
        $changed = 0;
        $reference = $statement->tables[0];
        $target = $reference instanceof TableReference ? $reference->alias->value ?? $reference->name->name->value : $table->definition->name;
        foreach ($matched as $index => $number) {
            $old = $table->data->rows[$number] ?? null;
            if ($old === null) {
                continue;
            }
            $store = new Store($context, $index + 1, $target);
            [$row, $assigned] = $this->assigned($old, $compiled, $table, $writer, $store);
            $triggers = new Triggers($references->session, $table);
            $row = $triggers->before('UPDATE', $row, $old, $context) ?? $row;
            if (!$this->differs($old, $row, $table)) {
                $triggers->after('UPDATE', $row, $old, $context);
                continue;
            }
            $row = $writer->refresh($row, $assigned);
            if (!(new Constrained($writer, $references, $selected))->updated($row, $old, $number, $statement->ignore)) {
                continue;
            }
            $references->session->transaction->write($table, $number);
            $table->data->update($number, $row);
            $changed++;
            $triggers->after('UPDATE', $row, $old, $context);
        }

        return $changed;
    }

    /**
     * Applies the assignments of an UPDATE to a row, left to right, each seeing the values the ones before it assigned, and computes its generated columns; answers the row and the positions assigned.
     *
     * DEFAULT assigns the default of the column, which an expression computes from the row so
     * far; a generated column assigned DEFAULT keeps being computed (verified on a live 8.4
     * server).
     *
     * @param list<int|float|string|null> $old
     * @param list<array{int, \MySqlMemory\Evaluation\Evaluable|DefaultRequest}> $compiled
     * @return array{list<int|float|string|null>, array<int, true>}
     *
     * @throws \MySqlMemory\Error\SqlError When a value is refused
     */
    public function assigned(array $old, array $compiled, StoredTable $table, Writer $writer, Store $store): array
    {
        $context = $writer->context;
        $definition = $table->definition;
        $frame = new Frame($context);
        $row = $old;
        $assigned = [];
        foreach ($compiled as [$position, $value]) {
            $column = $definition->columns[$position];
            $frame->row = (new TimestampZones())->local($table, $row, $context);
            if ($value instanceof DefaultRequest) {
                if ($column->generated === null) {
                    array_splice($row, $position, 1, [$this->defaulted($column, $writer, $frame, $store)]);
                    $assigned[$position] = true;
                }
                continue;
            }
            $stored = $store->value($value->evaluate($frame), $value->domain(), $column);
            array_splice($row, $position, 1, [$this->nonNull($stored, $column, $writer)]);
            $assigned[$position] = true;
        }

        return [$writer->generate($row, $store, fn (int $position, $value) => $this->nonNull($value, $definition->columns[$position], $writer)), $assigned];
    }

    /**
     * Answers the default a column takes for DEFAULT: its default, else NULL or the implicit default of its type with ER_NO_DEFAULT_FOR_FIELD, an error under a strict mode.
     *
     * @throws \MySqlMemory\Error\SqlError When the column has no default under a strict mode
     */
    public function defaulted(\MySqlMemory\Dictionary\ColumnDefinition $column, Writer $writer, Frame $frame, Store $store): int|float|string|null
    {
        [$has, $value] = $writer->default($column, $frame);
        if ($has || $column->autoIncrement) {
            return $value;
        }
        $store->adjust(DataError::NoDefaultForField, $column->name);

        return $column->nullable() ? null : $writer->implicit($column);
    }

    /**
     * Refuses NULL for a NOT NULL column under a strict mode, and otherwise stores the implicit default of the column with a warning.
     *
     * @throws \MySqlMemory\Error\SqlError When NULL is refused
     */
    public function nonNull(int|float|string|null $value, \MySqlMemory\Dictionary\ColumnDefinition $column, Writer $writer): int|float|string|null
    {
        if ($value !== null || $column->nullable()) {
            return $value;
        }
        if ($writer->context->strict) {
            throw DataError::BadNull->error($column->name);
        }
        $writer->context->warning(DataError::BadNull, $column->name);

        return $writer->implicit($column);
    }

    /**
     * Deletes the matched rows that are still there, each after the actions of the foreign keys that reference it; answers how many it deleted.
     *
     * A row a foreign key keeps is refused, or with IGNORE kept with a warning.
     *
     * @param list<int> $matched
     *
     * @throws \MySqlMemory\Error\SqlError When a foreign key keeps a row
     */
    public function delete(StoredTable $table, array $matched, References $references, bool $ignore): int
    {
        $deleted = 0;
        foreach ($matched as $number) {
            $row = $table->data->rows[$number] ?? null;
            if ($row === null) {
                continue;
            }
            $triggers = new Triggers($references->session, $table);
            $triggers->before('DELETE', null, $row, $references->context);
            try {
                $references->deleting($table, $row);
            } catch (\MySqlMemory\Error\SqlError $error) {
                if (!$ignore || $error->error !== \MySqlMemory\Error\Family\ConstraintError::RowIsReferenced) {
                    throw $error;
                }
                $references->context->diagnostics->warning($error->error, $error->getMessage());
                continue;
            }
            $references->session->transaction->write($table, $number);
            $table->data->delete($number);
            $deleted++;
            $triggers->after('DELETE', null, $row, $references->context);
        }

        return $deleted;
    }

    /**
     * Tells whether a row differs from its old values.
     *
     * @param list<int|float|string|null> $old
     * @param list<int|float|string|null> $new
     */
    public function differs(array $old, array $new, StoredTable $table): bool
    {
        foreach ($new as $position => $value) {
            $domain = $table->definition->columns[$position]->domain;
            if (($value === null) !== ($old[$position] === null) || ($value !== null && Order::key($value, $domain) !== Order::key($old[$position], $domain)) || ($value !== null && (string) $value !== (string) $old[$position])) {
                return true;
            }
        }

        return false;
    }
}
