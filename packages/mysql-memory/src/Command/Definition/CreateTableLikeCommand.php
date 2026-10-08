<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Heap;
use Override;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\NumberOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\NumberOption;
use SqlSemantics\Platform\MySql\Statement\Table\TableOption;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE TABLE ... LIKE: creates an empty table with the columns, indexes and options of another.
 *
 * The source is checked first: its database and the table must exist, and it cannot be the new
 * table itself (ER_NONUNIQ_TABLE). Then the database of the new table must exist, and a table of
 * its name is ER_TABLE_EXISTS_ERROR, or a note with IF NOT EXISTS. The new table numbers its
 * AUTO_INCREMENT values from 1. The statement commits the open transaction unless it creates a
 * temporary table. Every rule was verified on a live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-like.html,
 * https://dev.mysql.com/doc/refman/8.4/en/implicit-commit.html.
 *
 * @visibility MySqlMemory
 */
final class CreateTableLikeCommand implements Command
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
     * Creates the table.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $create = $operation->statement;
        assert($create instanceof CreateTableLike);
        $database = $session->variables->database;
        if (($create->name->schema === null || $create->source->schema === null) && $database === '') {
            throw ErrorCode::NoDatabase->error();
        }
        $dictionary = $session->instance->dictionary;
        $schema = $create->name->schema->value ?? $database;
        $name = $create->name->name->value;
        $sourceSchema = $create->source->schema->value ?? $database;
        if ($schema === $sourceSchema && $name === $create->source->name->value) {
            throw ErrorCode::NonUniqueTable->error($name);
        }
        if ($dictionary->schema($sourceSchema) === null) {
            throw ErrorCode::BadDatabase->error($sourceSchema);
        }
        $source = $dictionary->table($sourceSchema, $create->source->name->value);
        if ($source === null && isset($dictionary->schema($sourceSchema)?->views[$create->source->name->value])) {
            throw ErrorCode::WrongObject->error($sourceSchema, $create->source->name->value, 'BASE TABLE');
        }
        if ($source === null) {
            throw ErrorCode::NoSuchTable->error($sourceSchema, $create->source->name->value);
        }
        if ($create->temporaryWords === 0) {
            $session->transaction->commit();
        }
        $target = $dictionary->schema($schema);
        if ($target === null) {
            throw ErrorCode::BadDatabase->error($schema);
        }
        if ($target->table($name) !== null) {
            if (!$create->ifNotExists) {
                throw ErrorCode::TableExists->error($name);
            }
            $context->note(ErrorCode::TableExists, $name);

            return new Completion(0, 0, $context->diagnostics->count());
        }
        $from = TableLayout::of($source->definition);
        $options = array_values(array_filter($from->options, static fn (TableOption $option): bool => !$option instanceof NumberOption || $option->kind !== NumberOptionKind::AutoIncrement));
        $layout = new TableLayout(new QualifiedName(new Name($name), new Name($schema)), $from->columns, $from->keys, $options, $create->temporaryWords);
        $layout->undefaulted = $from->undefaulted;
        $layout->names = $from->names;
        $definition = (new TableRebuild($session, $context, $connection))->definition($layout, $dictionary->declarations(), false);
        $target->tables[$name] = new StoredTable($definition, new Heap());

        return new Completion(0, 0, $context->diagnostics->count());
    }
}
