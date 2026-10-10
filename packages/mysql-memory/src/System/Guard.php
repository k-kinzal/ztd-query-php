<?php

declare(strict_types=1);

namespace MySqlMemory\System;

use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Session\Session;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TableRenaming;
use SqlSemantics\Platform\MySql\Statement\Alter\TargetTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Platform\MySql\Statement\Dml\Delete;
use SqlSemantics\Platform\MySql\Statement\Dml\MultipleDelete;
use SqlSemantics\Platform\MySql\Statement\Dml\Update;
use SqlSemantics\Platform\MySql\Statement\Dml\WriteTarget;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Platform\MySql\Statement\Server\Lock\TableLock;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTable;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Platform\MySql\Statement\View\CreateView;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Node;

/**
 * Refuses the statements that would create, change or lock a table of the system databases.
 *
 * INFORMATION_SCHEMA holds views the server computes: creating, changing, dropping or locking a
 * table in it, or writing one, is denied for the database (ER_DBACCESS_DENIED_ERROR). The
 * tables of the Performance Schema refuse a write with ER_TABLEACCESS_DENIED_ERROR naming the
 * command. The emulator computes the tables of the mysql database from its accounts and
 * dictionary, and does not support writing them or changing their definition (verified on a
 * live 8.4.7 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/information-schema-introduction.html,
 * https://dev.mysql.com/doc/refman/8.4/en/performance-schema-table-characteristics.html.
 *
 * @visibility MySqlMemory
 */
final class Guard
{
    /**
     * Refuses a statement that creates, changes or locks a system table.
     *
     * @throws SqlError When the statement targets a table of a system database
     */
    public function check(Node $statement, Session $session): void
    {
        $system = $session->instance->dictionary->system;
        if ($system === null) {
            return;
        }
        $written = $this->written($statement);
        $defined = $this->defined($statement);
        foreach ([...$defined, ...$written] as $name) {
            $schema = $this->schema($name, $session);
            if (strcasecmp($schema, 'information_schema') === 0) {
                throw AccountError::DatabaseAccessDenied->error($session->user, explode('@', $session->variables->definer)[1] ?? '%', 'information_schema');
            }
        }
        foreach ($written as $name) {
            $table = $system->find($this->schema($name, $session), $name->name->value);
            if ($table !== null && $table->schema === 'performance_schema') {
                throw AccountError::TableAccessDenied->error($this->verb($statement), $session->user, $session->host, $table->name);
            }
            if ($table !== null) {
                throw StatementError::NotSupportedYet->error('writing a table of the mysql database');
            }
        }
        foreach ($defined as $name) {
            if ($system->find($this->schema($name, $session), $name->name->value) !== null) {
                throw StatementError::NotSupportedYet->error('changing a system table');
            }
        }
    }

    /**
     * Answers the tables a statement writes: the targets of a write and the tables an UPDATE names.
     *
     * @return list<QualifiedName>
     */
    public function written(Node $statement): array
    {
        $walker = new Walker();
        $written = [];
        foreach ($walker->find($statement, WriteTarget::class, false) as $target) {
            $written[] = $target->name;
        }
        foreach ($statement instanceof Update ? $statement->tables : [] as $relation) {
            foreach ($walker->find($relation, TableReference::class, false) as $reference) {
                $written[] = $reference->name;
            }
        }

        return $written;
    }

    /**
     * Answers the tables a statement creates, changes, renames or locks.
     *
     * @return list<QualifiedName>
     */
    public function defined(Node $statement): array
    {
        $walker = new Walker();
        $defined = match (true) {
            $statement instanceof CreateTable, $statement instanceof CreateTableLike => [$statement->name],
            $statement instanceof CreateView => [$statement->definition->name],
            $statement instanceof CreateIndex, $statement instanceof AlterTable, $statement instanceof TruncateTable => [$statement->table],
            default => [],
        };
        foreach ([...$walker->find($statement, TargetTable::class, false), ...$walker->find($statement, TableLock::class, false)] as $target) {
            $defined[] = $target instanceof TableLock ? $target->table : $target->name;
        }
        foreach ($walker->find($statement, TableRenaming::class, false) as $renaming) {
            array_push($defined, $renaming->from, $renaming->to);
        }

        return $defined;
    }

    /**
     * Answers the database a name is in: the one it is qualified by, else the current one.
     */
    public function schema(QualifiedName $name, Session $session): string
    {
        return $name->schema->value ?? $session->variables->database;
    }

    /**
     * Answers the command a write is denied as.
     */
    public function verb(Node $statement): string
    {
        return match (true) {
            $statement instanceof Update => 'UPDATE',
            $statement instanceof Delete, $statement instanceof MultipleDelete => 'DELETE',
            default => 'INSERT',
        };
    }
}
