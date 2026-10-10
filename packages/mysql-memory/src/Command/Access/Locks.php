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
use SqlSemantics\Platform\MySql\Statement\Query\With\CommonTableExpression;
use SqlSemantics\Platform\MySql\Statement\Relation\TableReference;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Statement;

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
     * Raises the error of the first table a statement uses without a lock that allows it.
     *
     * @throws \MySqlMemory\Error\SqlError When a table is not locked, or is written under a READ lock
     */
    public function check(Statement $statement, Session $session): void
    {
        if (!$statement instanceof Query && !$statement instanceof InsertRows && !$statement instanceof InsertSet && !$statement instanceof InsertQuery
            && !$statement instanceof Update && !$statement instanceof Delete && !$statement instanceof MultipleDelete) {
            return;
        }
        $common = array_map(static fn (CommonTableExpression $table): string => $table->name->value, (new Walker())->find($statement, CommonTableExpression::class));
        $written = $this->written($statement);
        foreach ((new Walker())->find($statement, TableReference::class) as $reference) {
            $this->allowed($session, $reference->name, $reference->alias->value ?? $reference->name->name->value, $common, in_array($reference, $written, true));
        }
        foreach ((new Walker())->find($statement, WriteTarget::class) as $target) {
            $this->allowed($session, $target->name, $target->alias->value ?? $target->name->name->value, $common, true);
        }
    }

    /**
     * Answers the table references a statement writes besides its write targets: the tables of an UPDATE, and the tables a multiple-table DELETE deletes from.
     *
     * @return list<TableReference>
     */
    public function written(Statement $statement): array
    {
        if ($statement instanceof Update) {
            return array_values(array_filter($statement->tables, static fn ($table): bool => $table instanceof TableReference));
        }
        if (!$statement instanceof MultipleDelete) {
            return [];
        }
        $targets = array_map(static fn (QualifiedName $name): string => strtolower($name->name->value), $statement->targets);

        return array_values(array_filter((new Walker())->find($statement, TableReference::class), static fn (TableReference $reference): bool => in_array(strtolower($reference->alias->value ?? $reference->name->name->value), $targets, true)));
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
        if ($session->instance->dictionary->table($schema, $name->name->value)?->definition->temporary === true) {
            return;
        }
        foreach ($session->locks as [$lockedSchema, $table, $alias, $exclusive]) {
            if ($lockedSchema === $schema && $table === $name->name->value && strcasecmp($alias, $used) === 0) {
                if ($write && !$exclusive) {
                    throw StatementError::TableNotLockedForWrite->error($used);
                }

                return;
            }
        }

        throw StatementError::TableNotLocked->error($used);
    }
}
