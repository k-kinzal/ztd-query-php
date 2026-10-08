<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Alter\RenameTable;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

/**
 * Executes RENAME TABLE: renames tables one after the other, all or none.
 *
 * Each new name is checked first, while the statement is read: an empty name, or one that ends
 * with a space, is ER_WRONG_TABLE_NAME, and a name longer than 64 characters
 * ER_TOO_LONG_IDENT. Then each rename in order: the databases must exist, the new name must be
 * free, and the table must exist; a temporary table is not renamed by this statement. A later
 * rename sees the names the earlier ones produced. The statement commits the open transaction.
 * Every rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/rename-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/identifier-length.html.
 *
 * @visibility MySqlMemory
 */
final class RenameTableCommand implements Command
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
     * Renames the tables.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof RenameTable);
        foreach ($statement->renamings as $renaming) {
            $this->valid($renaming->to->name->value);
        }
        $database = $session->variables->database;
        foreach ($statement->renamings as $renaming) {
            if (($renaming->from->schema === null || $renaming->to->schema === null) && $database === '') {
                throw ErrorCode::NoDatabase->error();
            }
        }
        $session->transaction->commit();
        $dictionary = $session->instance->dictionary;
        $names = [];
        foreach ($dictionary->schemas as $schema) {
            foreach ($schema->tables as $name => $table) {
                if (!$table->definition->temporary) {
                    $names[$schema->name . "\0" . $name] = $table;
                }
            }
        }
        $moves = [];
        foreach ($statement->renamings as $renaming) {
            $from = [$renaming->from->schema->value ?? $database, $renaming->from->name->value];
            $to = [$renaming->to->schema->value ?? $database, $renaming->to->name->value];
            foreach ([$from[0], $to[0]] as $schema) {
                if ($dictionary->schema($schema) === null) {
                    throw ErrorCode::BadDatabase->error($schema);
                }
            }
            if (isset($names[$to[0] . "\0" . $to[1]])) {
                throw ErrorCode::TableExists->error($to[1]);
            }
            $table = $names[$from[0] . "\0" . $from[1]] ?? null;
            if ($table === null) {
                throw ErrorCode::NoSuchTable->error($from[0], $from[1]);
            }
            unset($names[$from[0] . "\0" . $from[1]]);
            $names[$to[0] . "\0" . $to[1]] = $table;
            $moves[] = [$table, $to];
        }
        foreach ($moves as [$table]) {
            unset($dictionary->schemas[$table->definition->schema]->tables[$table->definition->name]);
        }
        foreach ($moves as [$table, $to]) {
            $table->definition = $this->renamed($session, $context, $connection, $table, $to[0], $to[1]);
        }
        foreach ($names as $table) {
            $dictionary->schemas[$table->definition->schema]->tables[$table->definition->name] = $table;
        }

        return new Completion();
    }

    /**
     * Refuses a name no table can have.
     *
     * @throws \MySqlMemory\Error\SqlError When the name is empty, ends with a space or is too long
     */
    public function valid(string $name): void
    {
        if ($name === '' || str_ends_with($name, ' ')) {
            throw ErrorCode::WrongTableName->error($name);
        }
        if (mb_strlen($name) > 64) {
            throw ErrorCode::TooLongIdentifier->error($name);
        }
    }

    /**
     * Refuses a new name whose database does not exist or that a table has.
     *
     * @throws \MySqlMemory\Error\SqlError When the name cannot be taken
     */
    public function vacant(Session $session, string $schema, string $name): void
    {
        $this->valid($name);
        if ($session->instance->dictionary->schema($schema) === null) {
            throw ErrorCode::BadDatabase->error($schema);
        }
        if ($session->instance->dictionary->table($schema, $name) !== null) {
            throw ErrorCode::TableExists->error($name);
        }
    }

    /**
     * Answers the definition of a table under another name, declared again so that statements bind to it by its new name.
     *
     * @throws \MySqlMemory\Error\SqlError When the definition cannot be declared again
     */
    public function renamed(Session $session, Context $context, Connection $connection, StoredTable $table, string $schema, string $name): TableDefinition
    {
        $layout = TableLayout::of($table->definition);
        $layout->name = new QualifiedName(new Name($name), new Name($schema));
        $others = array_values(array_filter($session->instance->dictionary->declarations(), static fn ($declaration): bool => $declaration !== $table->definition->declaration));

        return (new TableRebuild($session, $context, $connection))->definition($layout, $others, false);
    }
}
