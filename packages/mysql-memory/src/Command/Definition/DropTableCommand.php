<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Heap;
use MySqlMemory\Storage\References;
use Override;
use SqlSemantics\Platform\MySql\Statement\Alter\DropTable;
use SqlSemantics\Platform\MySql\Statement\Alter\TruncateTable;
use SqlSemantics\Statement\Operation;

/**
 * Executes DROP TABLE and TRUNCATE TABLE.
 *
 * DROP TABLE of missing tables names all of them in one error (ER_BAD_TABLE_ERROR); with IF
 * EXISTS each missing table is a note. TRUNCATE removes every row and resets AUTO_INCREMENT.
 * DROP TABLE drops a temporary table of the session before a base table of the name, and DROP
 * TEMPORARY TABLE only a temporary one; neither commits the open transaction for a temporary
 * table. While foreign_key_checks is on, a table another table references cannot be dropped
 * unless the statement drops that table too, nor emptied (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/drop-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/truncate-table.html.
 *
 * @visibility MySqlMemory
 */
final class DropTableCommand implements Command
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
     * Drops or empties the tables.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $dictionary = $session->instance->dictionary;
        $database = $session->variables->database;
        if ($statement instanceof TruncateTable) {
            return $this->truncate($statement, $session, $context);
        }
        assert($statement instanceof DropTable);
        $missing = [];
        $found = [];
        foreach ($statement->tables as $target) {
            $name = $target->name;
            $schema = $name->schema->value ?? $database;
            $table = $statement->temporary ? ($dictionary->schema($schema) === null ? null : $session->temporaries->table($schema, $name->name->value)) : $dictionary->table($schema, $name->name->value);
            if ($table === null) {
                $missing[] = $schema . '.' . $name->name->value;
            } else {
                $found[] = [$schema, $name->name->value, $table];
            }
        }
        if (!$statement->temporary) {
            $session->transaction->commit();
        }
        if ($missing !== [] && !$statement->ifExists) {
            throw SchemaError::BadTable->error(implode(',', $missing));
        }
        $this->referenced(array_map(static fn (array $entry): StoredTable => $entry[2], $found), $session, $context);
        foreach ($missing as $name) {
            $context->note(SchemaError::BadTable, $name);
        }
        foreach ($found as [$schema, $name, $table]) {
            $dictionary->release($table, $schema, $name);
            if ($table->definition->temporary && $session->transaction->open) {
                $session->transaction->temporaries['dropped'] = true;
            }
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Empties a table for TRUNCATE TABLE, after committing the open transaction unless the table is temporary.
     *
     * @throws SqlError When the table does not exist or another table references it
     */
    public function truncate(TruncateTable $statement, Session $session, Context $context): Reply
    {
        $schema = $statement->table->schema->value ?? $session->variables->database;
        $table = $session->instance->dictionary->table($schema, $statement->table->name->value);
        if ($table === null || !$table->definition->temporary) {
            $session->transaction->commit();
        }
        if ($table === null) {
            throw QueryError::NoSuchTable->error($schema, $statement->table->name->value);
        }
        $this->truncated($table, $session, $context);
        $table->data = new Heap();
        $table->updated = null;
        $table->statistics = array_intersect_key($table->statistics, ['table' => true]);

        return new Completion();
    }

    /**
     * Refuses to drop a table a foreign key of a table the statement keeps references, while foreign_key_checks is on (ER_FK_CANNOT_DROP_PARENT); MySQL 5.6 and 5.7 refuse it with ER_ROW_IS_REFERENCED after a warning, numbered as the handler error 152 in 5.6 (verified on live 5.6.51 and 5.7.44 servers).
     *
     * @param list<StoredTable> $tables The tables the statement drops
     *
     * @throws SqlError When a kept table references a dropped one
     */
    public function referenced(array $tables, Session $session, Context $context): void
    {
        $references = new References($session, $context);
        if (!$references->enabled()) {
            return;
        }
        foreach ($tables as $table) {
            foreach ($references->children($table) as [$child, $key]) {
                if (!in_array($child, $tables, true) && $session->settings()->legacy()) {
                    $context->diagnostics->warning($session->settings()->release() === \SqlSemantics\Contract\GrammarRelease::MySql5651 ? 152 : ConstraintError::RowIsReferenced->value, ConstraintError::RowIsReferenced->message(''));

                    throw ConstraintError::ParentRowReferenced->error();
                }
                if (!in_array($child, $tables, true)) {
                    throw ConstraintError::DropReferencedTable->error($table->definition->name, $key->name, $child->definition->name);
                }
            }
        }
    }

    /**
     * Refuses to empty a table a foreign key of another table references, while foreign_key_checks is on (ER_TRUNCATE_ILLEGAL_FK).
     *
     * @throws SqlError When another table references it
     */
    public function truncated(StoredTable $table, Session $session, Context $context): void
    {
        $references = new References($session, $context);
        if (!$references->enabled()) {
            return;
        }
        foreach ($references->children($table) as [$child, $key]) {
            if ($child !== $table) {
                throw ConstraintError::TruncateReferenced->error('`' . $child->definition->schema . '`.`' . $child->definition->name . '`, ' . ($session->settings()->legacy() ? $key->text($child->definition, true, true) : 'CONSTRAINT `' . $key->name . '`'));
            }
        }
    }
}
