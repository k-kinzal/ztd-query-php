<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use MySqlMemory\Storage\Heap;
use Override;
use SqlSemantics\Platform\MySql\Statement\Table\CreateTableLike;
use SqlSemantics\Platform\MySql\Statement\Table\Key\CheckConstraint;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ForeignKey;
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
 * AUTO_INCREMENT values from 1, keeps the CHECK constraints of the source under names the
 * server gives them, and has no foreign keys. The statement commits the open transaction unless it
 * creates a temporary table. Every rule was verified on a live 8.4 server.
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
        $dictionary = $session->instance->dictionary;
        $source = $this->source($create, $session);
        $schema = $create->name->schema->value ?? $database;
        $name = $create->name->name->value;
        if ($create->temporaryWords === 0) {
            $session->transaction->commit();
        }
        $target = $dictionary->schema($schema);
        if ($target === null) {
            throw QueryError::BadDatabase->error($schema);
        }
        if ($create->temporaryWords > 0 ? $session->temporaries->table($schema, $name) !== null : $target->table($name) !== null) {
            if (!$create->ifNotExists) {
                throw SchemaError::TableExists->error($name);
            }
            $context->note(SchemaError::TableExists, $name);

            return new Completion(0, 0, $context->diagnostics->count());
        }
        $layout = $this->layout($source, new QualifiedName(new Name($name), new Name($schema)), $create->temporaryWords);
        $definition = (new TableRebuild($session, $context, $connection))->definition($layout, $dictionary->declarations(), false);
        $dictionary->store(new StoredTable($definition, new Heap()));
        if ($definition->temporary && $session->transaction->open) {
            $session->transaction->temporaries['created'] = true;
        }

        return new Completion(0, 0, $context->diagnostics->count());
    }

    /**
     * Answers the table to copy: the database of the source and the table must exist, and the source
     * cannot be the new table itself.
     *
     * @throws \MySqlMemory\Error\SqlError When there is no default database, the source is the new table, or the source does not exist or is a view
     */
    public function source(CreateTableLike $create, Session $session): StoredTable
    {
        $database = $session->variables->database;
        if (($create->name->schema === null || $create->source->schema === null) && $database === '') {
            throw QueryError::NoDatabase->error();
        }
        $dictionary = $session->instance->dictionary;
        $schema = $create->name->schema->value ?? $database;
        $name = $create->name->name->value;
        $sourceSchema = $create->source->schema->value ?? $database;
        if ($schema === $sourceSchema && $name === $create->source->name->value) {
            throw QueryError::NonUniqueTable->error($name);
        }
        if ($dictionary->schema($sourceSchema) === null) {
            throw QueryError::BadDatabase->error($sourceSchema);
        }
        $source = $dictionary->table($sourceSchema, $create->source->name->value);
        if ($source === null && isset($dictionary->schema($sourceSchema)?->views[$create->source->name->value])) {
            throw SchemaError::WrongObject->error($sourceSchema, $create->source->name->value, 'BASE TABLE');
        }
        if ($source === null) {
            throw QueryError::NoSuchTable->error($sourceSchema, $create->source->name->value);
        }

        return $source;
    }

    /**
     * Answers the layout of the new table: that of the source without its AUTO_INCREMENT option and
     * its foreign keys, with its CHECK constraints left for the server to name.
     *
     * @throws \MySqlMemory\Error\SqlError When the statement that declares the source is not known
     */
    public function layout(StoredTable $source, QualifiedName $name, int $temporaryWords): TableLayout
    {
        $from = TableLayout::of($source->definition);
        $options = array_values(array_filter($from->options, static fn (TableOption $option): bool => !$option instanceof NumberOption || $option->kind !== NumberOptionKind::AutoIncrement));
        $keys = [];
        foreach ($from->keys as [$element, $key]) {
            if ($element instanceof CheckConstraint) {
                $keys[] = [new CheckConstraint($element->condition, null, $element->enforced), $key];
            } elseif (!$element instanceof ForeignKey) {
                $keys[] = [$element, $key];
            }
        }
        $layout = new TableLayout($name, $from->columns, $keys, $options, $temporaryWords);
        $layout->undefaulted = $from->undefaulted;
        $layout->names = $from->names;

        return $layout;
    }
}
