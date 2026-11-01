<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Access;

use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertQuery;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertRows;
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertSet;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Statement\Identifier\QualifiedName;

/**
 * Checks that a query or a data change uses only the tables LOCK TABLES locked, by the names it locked them under.
 *
 * While a session holds table locks, a table it reads must be locked under the name the
 * statement uses, its alias or its name, and a table it writes must hold a WRITE lock. A
 * temporary table and a common table expression need no lock. Every rule was verified on a live
 * 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/lock-tables.html.
 *
 * @visibility MySqlMemory
 */
final class Locks
{
    /**
     * Requires a persistent table's WRITE metadata lock, regardless of its alias.
     * Temporary tables cannot supply this lock. CREATE/DROP TRIGGER and TRUNCATE
     * use MySQL's find_table_for_mdl_upgrade before opening the table.
     *
     * @throws \MySqlMemory\Error\SqlError When explicit locks contain no matching WRITE lock
     */
    public function metadata(Session $session, string $schema, string $name): void
    {
        if ($session->locks === []) {
            return;
        }
        $found = false;
        foreach ($session->locks as $lock) {
            if ($lock->temporary || $lock->schema !== $schema || $lock->table !== $name) {
                continue;
            }
            $found = true;
            if ($lock->write) {
                return;
            }
        }
        throw ($found ? StatementError::TableNotLockedForWrite : StatementError::TableNotLocked)->error($name);
    }

    /**
     * Raises the error of the first table a statement uses without a lock that allows it.
     * MySQL 5.6 explains INSERT/REPLACE VALUES and SET before acquiring write locks;
     * from 5.7 it acquires them before publishing the plan (sql_insert.cc).
     *
     * @throws \MySqlMemory\Error\SqlError When a table is not locked, or is written under a READ lock
     */
    public function check(MySqlStatement $operation, Session $session): void
    {
        (new AccountTables())->check($operation, $session);
        $statement = $operation instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain ? $operation->statement : $operation;
        if ($operation instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Explain\Explain && $session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 && ($statement instanceof InsertRows || $statement instanceof InsertSet)) {
            return;
        }
        if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot && !$statement instanceof InsertRows && !$statement instanceof InsertSet && !$statement instanceof InsertQuery
            && !$statement instanceof Update && !$statement instanceof Delete && !$statement instanceof MultipleDelete) {
            return;
        }
        foreach ($this->written($statement) as $reference) {
            $this->allowed($session, $reference->name, $reference->alias->value ?? $reference->name->name->value, [], true);
        }
        foreach ((new Walker())->find($statement, WriteTarget::class) as $target) {
            $this->allowed($session, $target->name, $target->alias->value ?? $target->name->name->value, [], true);
        }
    }

    /**
     * Requires a separate explicit lock for every opened occurrence before name resolution.
     * Resolved common-table uses expand their inputs; unused definitions stay unopened.
     *
     * @throws \MySqlMemory\Error\SqlError When an occurrence lacks its own lock
     */
    public function opened(\SqlSemantics\Statement\Node $statement, Session $session): void
    {
        if ($session->locks === []) {
            return;
        }
        $seen = [];
        $references = [...(new Walker())->find($statement, WriteTarget::class), ...(new \MySqlMemory\Session\Access\OpenedTables())->references($statement)];
        foreach ($references as $reference) {
            $name = $reference->name();
            $schema = $name->schema->value ?? $session->variables->database;
            if ($session->temporaries->table($schema, $name->name->value) !== null) {
                continue;
            }
            $used = $reference->alias()->value ?? $name->name->value;
            $this->allowed($session, $name, $used, [], false);
            $key = $schema . "\0" . $name->name->value . "\0" . strtolower($used);
            if (isset($seen[$key])) {
                throw StatementError::TableNotLocked->error($used);
            }
            $seen[$key] = true;
        }
    }

    /**
     * Answers the table references a statement writes besides its write targets: the tables of an UPDATE, and the tables a multiple-table DELETE deletes from.
     *
     * @return list<TableReference>
     */
    public function written(MySqlStatement $operation): array
    {
        $statement = $operation;
        if ($statement instanceof Update) {
            return array_values(array_filter($statement->tables, static fn ($table): bool => $table instanceof TableReference));
        }
        if (!$statement instanceof MultipleDelete) {
            return [];
        }
        $targets = array_map($statement->input->relation(...), $statement->targets);

        return array_values(array_filter($targets, static fn ($reference): bool => $reference instanceof TableReference));
    }

    /**
     * Opens every required name before checking write permissions.
     *
     * Account commands use this order for their implicitly opened grant tables.
     *
     * @param list<QualifiedName> $tables The tables in opening order
     * @throws \MySqlMemory\Error\SqlError When a required name or write lock is missing
     */
    public function required(Session $session, array $tables, bool $write): void
    {
        foreach ($tables as $table) {
            $this->allowed($session, $table, $table->name->value, [], false);
        }
        foreach ($write ? $tables : [] as $table) {
            $this->allowed($session, $table, $table->name->value, [], true);
        }
    }

    /**
     * Refuses a table used under a name no lock allows.
     *
     * @param list<string> $common The names of the common table expressions of the statement
     *
     * @throws \MySqlMemory\Error\SqlError When no lock allows the use
     */
    public function allowed(Session $session, QualifiedName $name, string $used, array $common, bool $write): void
    {
        if ($name->schema === null && in_array($name->name->value, $common, true)) {
            return;
        }
        $schema = $name->schema->value ?? $session->variables->database;
        if ($session->instance->dictionary->table($schema, $name->name->value, $session->temporaries)?->definition->temporary === true) {
            return;
        }
        foreach ($session->locks as $lock) {
            if (!$lock->temporary && $lock->schema === $schema && $lock->table === $name->name->value && strcasecmp($lock->alias, $used) === 0) {
                if ($write && !$lock->write) {
                    throw StatementError::TableNotLockedForWrite->error($used);
                }

                return;
            }
        }

        throw StatementError::TableNotLocked->error($used);
    }
}
