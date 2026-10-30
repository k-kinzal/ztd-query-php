<?php

declare(strict_types=1);

namespace MySqlMemory\System;

use LogicException;
use MySqlMemory\Error\Family\AccountError;
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
 * other system schemas require command- and table-specific mutation semantics. An
 * unimplemented mutation fails internally instead of inventing a privilege denial
 * or an ER_NOT_SUPPORTED_YET result that could match the native server by accident.
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
     * @throws SqlError When the statement attempts to change INFORMATION_SCHEMA
     * @throws LogicException When system-table mutation semantics are not implemented
     */
    public function check(Node $statement, Session $session, \SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts): void
    {
        $system = $session->instance->dictionary->system;
        if ($system === null) {
            return;
        }
        $written = $this->written($statement, $facts);
        $defined = $this->defined($statement);
        foreach ([...$defined, ...$written] as $name) {
            $schema = $this->schema($name, $session);
            if (strcasecmp($schema, 'information_schema') === 0) {
                throw AccountError::DatabaseAccessDenied->error($session->user, $session->variables->definer->host, 'information_schema');
            }
        }
        foreach ($written as $name) {
            $table = $system->find($this->schema($name, $session), $name->name->value);
            if ($table !== null) {
                throw new LogicException('No system table ' . $this->verb($statement) . ' implementation for ' . $table->schema . '.' . $table->name . ' on mysql-' . $session->instance->version . '.');
            }
        }
        foreach ($defined as $name) {
            $table = $system->find($this->schema($name, $session), $name->name->value);
            if ($table !== null && $statement instanceof \SqlSemantics\Platform\MySql\Statement\Server\Lock\LockTables && $table->schema === 'mysql' && in_array($table->name, ['servers', ...\MySqlMemory\Command\Access\AccountTables::LEGACY, ...\MySqlMemory\Command\Access\AccountTables::MODERN], true)) {
                continue;
            }
            if ($table !== null) {
                throw new LogicException('No system table definition or lock implementation for ' . $table->schema . '.' . $table->name . ' on mysql-' . $session->instance->version . '.');
            }
        }
    }

    /**
     * Answers explicit targets and the input occurrences bound to assigned columns.
     *
     * @return list<QualifiedName>
     */
    public function written(Node $statement, \SqlSemantics\Platform\MySql\Statement\StatementSemantics $facts): array
    {
        $walker = new Walker();
        $written = [];
        foreach ($walker->find($statement, WriteTarget::class, false) as $target) {
            $written[] = $target->name;
        }
        if ($statement instanceof Update) {
            $inputs = (new \SqlSemantics\Platform\MySql\Rules\Dml\TargetReferences())->occurrences($statement->tables);
            if (count($inputs) === 1 && $inputs[0] instanceof TableReference) {
                $written[] = $inputs[0]->name;
            } else {
                foreach ($statement->assignments as $assignment) {
                    $resolution = $assignment->column->scalarInformation()->resolution;
                    if ($resolution instanceof \SqlSemantics\Statement\Reference\Column\ResolvedColumn && $resolution->relation instanceof TableReference) {
                        $written[] = $resolution->relation->name;
                    }
                }
            }
        }
        if (!$statement instanceof MultipleDelete) {
            return $written;
        }
        foreach ($statement->targets as $target) {
            $relation = $statement->input->relation($target);
            if ($relation instanceof TableReference) {
                $written[] = $relation->name;
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
