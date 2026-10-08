<?php

declare(strict_types=1);

namespace MySqlMemory\Plan;

use MySqlMemory\Concurrency\LockMode;
use MySqlMemory\Error\Family\TransactionError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Plan\Path\AccessPath;
use MySqlMemory\Plan\Path\Source\TableScan;
use MySqlMemory\Plan\Path\Transform\Filter;
use MySqlMemory\Plan\Path\Transform\Lock;
use MySqlMemory\Session\Transaction;
use SqlSemantics\Platform\MySql\Statement as MySql;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockedRowAction;
use SqlSemantics\Platform\MySql\Statement\Query\Locking\LockStrength;
use SqlSemantics\Platform\MySql\Statement\Query\Select;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;

/**
 * Decides which tables of a query block a read locks, and in which mode.
 *
 * FOR UPDATE locks the rows a block returns exclusively, FOR SHARE and LOCK IN SHARE MODE shared,
 * each with NOWAIT or SKIP LOCKED; OF names the tables a clause locks, else it locks every table of
 * the block. A block without a clause reads consistently, except under SERIALIZABLE inside a
 * transaction that spans statements, where it locks shared, and in a statement that writes, whose
 * reads of other tables lock shared under REPEATABLE READ and SERIALIZABLE. The tables of
 * derived tables and subqueries are locked by their own blocks only. FOR UPDATE of a table that is
 * not temporary is refused in a read-only transaction.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-locks-set.html,
 * https://dev.mysql.com/doc/refman/8.4/en/innodb-transaction-isolation-levels.html.
 *
 * @visibility MySqlMemory
 */
final class Locking
{
    /**
     * @param Planner $planner The planner of the statement
     */
    public function __construct(public readonly Planner $planner)
    {
    }

    /**
     * Wraps the rows of a block's FROM clause and its WHERE filter into a locking read when the block locks a table.
     *
     * @param array<int, true> $written The occurrences the statement writes, which it locks exclusively, by object id
     *
     * @throws SqlError When FOR UPDATE runs in a read-only transaction
     */
    public function lock(?Select $select, Scope $scope, AccessPath $input, ?Filter $filter, array $written = []): AccessPath
    {
        $transaction = $this->transaction();
        if ($transaction === null || $scope->scans === []) {
            return $filter ?? $input;
        }
        $default = $this->writes() ? $transaction->access->sourceReads() : $transaction->access->plainReads();
        if ($default === null && $written === [] && ($select->locking ?? []) === []) {
            return $filter ?? $input;
        }
        $targets = [];
        foreach ($scope->scans as $id => $scan) {
            $target = $this->target($select, $scope, $id, $scan, $default, $written, $transaction);
            if ($target !== null) {
                $targets[] = $target;
            }
        }
        if ($targets === []) {
            return $filter ?? $input;
        }

        return new Lock($filter->input ?? $input, $filter, $targets);
    }

    /**
     * Answers how a locking read locks one table occurrence of a block: its scan, the offset of its columns in a row, the mode and the action; null when the read leaves it unlocked.
     *
     * An occurrence the statement writes is locked exclusively, one a locking clause covers as the
     * clause says, any other in the default mode of the read.
     *
     * @param array<int, true> $written The occurrences the statement writes, by object id
     *
     * @return array{TableScan, int, LockMode, LockedRowAction|null}|null
     *
     * @throws SqlError When FOR UPDATE of a table that is not temporary runs in a read-only transaction
     */
    public function target(?Select $select, Scope $scope, int $id, TableScan $scan, ?LockMode $default, array $written, Transaction $transaction): ?array
    {
        [$mode, $action] = isset($written[$id]) ? [LockMode::Exclusive, null] : ($this->clause($select, $scope, $id) ?? [$default, null]);
        if ($mode === null) {
            return null;
        }
        if ($mode === LockMode::Exclusive && !isset($written[$id]) && $transaction->readOnly && !$scan->table->definition->temporary) {
            throw TransactionError::ReadOnlyTransaction->error();
        }

        return [$scan, $scope->offsets[$id] ?? 0, $mode, $action];
    }

    /**
     * Answers the lock mode and action the locking clause of a block that covers an occurrence gives it, or null when none covers it.
     *
     * @return array{LockMode, LockedRowAction|null}|null
     */
    public function clause(?Select $select, Scope $scope, int $id): ?array
    {
        $occurrence = null;
        foreach ($scope->nodes as $node) {
            if (spl_object_id($node) === $id && $node instanceof TableReference) {
                $occurrence = $node;
            }
        }
        foreach ($select->locking ?? [] as $clause) {
            $covers = $clause->tables === [];
            foreach ($clause->tables as $name) {
                $covers = $covers || ($occurrence !== null && strcasecmp($name->name->value, $occurrence->alias->value ?? $occurrence->name->name->value) === 0);
            }
            if ($covers) {
                return [$clause->strength === LockStrength::Update ? LockMode::Exclusive : LockMode::Shared, $clause->action];
            }
        }

        return null;
    }

    /**
     * Tells whether the statement planned writes rows: its reads of other tables lock them as a write requires.
     */
    public function writes(): bool
    {
        $statement = $this->planner->statement;

        return $statement instanceof MySql\Dml\Insert\InsertRows || $statement instanceof MySql\Dml\Insert\InsertSet || $statement instanceof MySql\Dml\Insert\InsertQuery
            || $statement instanceof MySql\Dml\Update || $statement instanceof MySql\Dml\Delete || $statement instanceof MySql\Dml\MultipleDelete
            || $statement instanceof MySql\Table\CreateTable;
    }

    /**
     * Answers the transaction of the session the statement runs in, or null outside a session.
     */
    public function transaction(): ?Transaction
    {
        $variables = $this->planner->compiler->connection->variables;

        return $variables->instance->transactions->of($variables->connection);
    }
}
