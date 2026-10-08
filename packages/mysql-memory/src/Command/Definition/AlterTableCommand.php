<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\StoredTable;
use MySqlMemory\Dictionary\TableDefinition;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Compile\Walker;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Problems;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterCommand;
use SqlSemantics\Platform\MySql\Statement\Alter\AlterTable;
use SqlSemantics\Platform\MySql\Statement\Alter\Column\AddColumns;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\AddConstraint;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\DropElement;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\ElementKind;
use SqlSemantics\Platform\MySql\Statement\Alter\Command\RenameTo;
use SqlSemantics\Platform\MySql\Statement\Alter\DropIndex;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\AlterModifier;
use SqlSemantics\Platform\MySql\Statement\Alter\Modifier\ValidationOption;
use SqlSemantics\Platform\MySql\Statement\Alter\Partition\ExchangePartition;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\DuplicateColumn;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\TableExists;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownAlterChoice;
use SqlSemantics\Platform\MySql\Statement\Alter\Problem\UnknownColumn;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Name\ColumnName;
use SqlSemantics\Platform\MySql\Statement\Table\CreateIndex;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ColumnPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ExpressionPart;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexAlgorithm;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexDefinition;
use SqlSemantics\Platform\MySql\Statement\Table\Key\IndexKind;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexParser;
use SqlSemantics\Platform\MySql\Statement\Table\Key\Option\IndexUsing;
use SqlSemantics\Platform\MySql\Statement\Table\Problem\UnknownKeyColumn;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;
use SqlSemantics\Statement\Reference\Table\MissingTable;

/**
 * Executes ALTER TABLE, CREATE INDEX and DROP INDEX: changes the definition and the rows of a table.
 *
 * CREATE INDEX is ALTER TABLE ... ADD INDEX and DROP INDEX is ALTER TABLE ... DROP INDEX. The
 * statement commits the open transaction. The actions change the layout of the table
 * (TableChange), which is declared again and fills the table with its rows converted
 * (TableRebuild); nothing changes when a step fails. A statement that only renames the table, or
 * only names ALGORITHM or LOCK, answers no information. ALGORITHM=INPLACE and LOCK=NONE are
 * refused for a change of a column type, which copies the table. Every rule was verified on a
 * live 8.4 server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-table.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-index.html,
 * https://dev.mysql.com/doc/refman/8.4/en/drop-index.html.
 *
 * @visibility MySqlMemory
 */
final class AlterTableCommand implements Command
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
     * Changes the table.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof AlterTable || $statement instanceof CreateIndex || $statement instanceof DropIndex);
        [$name, $commands] = $this->request($statement);
        $this->read($commands, $operation);
        $database = $session->variables->database;
        $schema = $name->schema->value ?? $database;
        if ($schema === '') {
            throw ErrorCode::NoDatabase->error();
        }
        $session->transaction->commit();
        $table = $session->instance->dictionary->table($schema, $name->name->value);
        if ($table === null && isset($session->instance->dictionary->schema($schema)?->views[$name->name->value])) {
            throw ErrorCode::WrongObject->error($schema, $name->name->value, 'BASE TABLE');
        }
        if ($table === null) {
            throw $session->instance->dictionary->schema($schema) === null ? ErrorCode::BadDatabase->error($schema) : ErrorCode::NoSuchTable->error($schema, $name->name->value);
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if (!$diagnostic instanceof MissingTable && !$diagnostic instanceof UnknownColumn && !$diagnostic instanceof DuplicateColumn && !$diagnostic instanceof TableExists && !$diagnostic instanceof UnknownKeyColumn && !$diagnostic instanceof UnknownAlterChoice) {
                throw (new Problems())->error($diagnostic, $session, 'field list', $statement);
            }
        }
        foreach ((new Walker())->find($statement, FunctionCall::class) as $call) {
            if (Problems::undeclared($call, $operation)) {
                throw (new Problems())->routine($call, $session);
            }
        }
        $layout = TableLayout::of($table->definition);
        $change = new TableChange($layout, $table->definition->name, $database, $session->settings()->release());
        $change->apply($commands);
        $target = $layout->name->schema->value ?? $schema;
        $renamed = $target !== $schema || $layout->name->name->value !== $table->definition->name;
        if ($renamed) {
            (new RenameTableCommand())->vacant($session, $target, $layout->name->name->value);
        }
        if ($change->toggled) {
            $context->note(ErrorCode::IllegalHa, $table->definition->name);
        }
        if (!$change->changes && $change->algorithm !== 'copy') {
            if ($renamed) {
                $table->definition = (new RenameTableCommand())->renamed($session, $context, $connection, $table, $target, $layout->name->name->value);
                $this->move($session, $table, $schema, $name->name->value);
            }

            return new Completion(0, 0, $context->diagnostics->count());
        }
        $rebuild = new TableRebuild($session, $context, $connection);
        $definition = $rebuild->definition($layout, $this->others($session, $table));
        $origins = array_map(static fn (array $column): ?int => $column[1], $layout->columns);
        $copies = $this->copies($table->definition, $definition, $origins, $change, $context);
        $data = $rebuild->rows($table, $definition, $origins);
        if ($change->ordered && $definition->primaryKey() !== null) {
            $context->warning(ErrorCode::UnknownError, 'ORDER BY ignored as there is a user-defined clustered index in the table \'' . $definition->name . '\'');
        }
        $records = $copies ? count($data->rows) : 0;
        $table->definition = $definition;
        $table->data = $data;
        $table->histograms = array_intersect_key($table->histograms, array_flip(array_map(static fn ($column): string => strtolower($column->name), $definition->columns)));
        if ($renamed) {
            $this->move($session, $table, $schema, $name->name->value);
        }
        $warnings = $context->diagnostics->count();

        return new Completion($records, 0, $warnings, 'Records: ' . $records . '  Duplicates: 0  Warnings: ' . $warnings);
    }

    /**
     * Raises the first problem the server finds while it reads the statement, before it opens the table.
     *
     * A full-text parser that is not installed comes first, then an unknown ALGORITHM or LOCK, a
     * new table name no table can have, an order written for a key part of a full-text, spatial
     * or hash index, and WITH VALIDATION outside a partition exchange (verified on a live 8.4
     * server).
     *
     * @param list<AlterCommand> $commands
     *
     * @throws \MySqlMemory\Error\SqlError When the statement has such a problem
     */
    public function read(array $commands, Operation $operation): void
    {
        $indexes = [];
        foreach ($commands as $command) {
            $elements = $command instanceof AddColumns ? $command->elements : ($command instanceof AddConstraint ? [$command->element] : []);
            foreach ($elements as $element) {
                if ($element instanceof IndexDefinition) {
                    $indexes[] = $element;
                }
            }
        }
        foreach ($indexes as $index) {
            foreach ($index->options as $option) {
                if ($option instanceof IndexParser && strcasecmp($option->parser->value, 'ngram') !== 0) {
                    throw ErrorCode::FunctionNotDefined->error($option->parser->value);
                }
            }
        }
        foreach ($operation->facts->diagnostics as $diagnostic) {
            if ($diagnostic instanceof UnknownAlterChoice) {
                throw ($diagnostic->lock ? ErrorCode::UnknownAlterLock : ErrorCode::UnknownAlterAlgorithm)->error($diagnostic->name->value);
            }
        }
        foreach ($commands as $command) {
            if ($command instanceof RenameTo) {
                (new RenameTableCommand())->valid($command->table->name->value);
            }
        }
        foreach ($indexes as $index) {
            $hashed = $index->algorithm === IndexAlgorithm::Hash || array_filter($index->options, static fn ($option): bool => $option instanceof IndexUsing && $option->algorithm === IndexAlgorithm::Hash) !== [];
            $ordered = array_filter($index->parts, static fn ($part): bool => ($part instanceof ColumnPart || $part instanceof ExpressionPart) && $part->direction !== null) !== [];
            if ($ordered && ($hashed || $index->kind === IndexKind::FullText || $index->kind === IndexKind::Spatial)) {
                throw ErrorCode::WrongUsage->error('spatial/fulltext/hash index', 'explicit index order');
            }
        }
        $validated = array_filter($commands, static fn (AlterCommand $command): bool => $command instanceof ValidationOption) !== [];
        $exchanged = array_filter($commands, static fn (AlterCommand $command): bool => $command instanceof ExchangePartition) !== [];
        if ($validated && !$exchanged) {
            throw ErrorCode::WrongUsage->error('ALTER', 'WITH VALIDATION');
        }
    }

    /**
     * Answers the table a statement changes and its actions.
     *
     * @return array{QualifiedName, list<AlterCommand>}
     */
    public function request(AlterTable|CreateIndex|DropIndex $statement): array
    {
        if ($statement instanceof AlterTable) {
            return [$statement->table, $statement->commands];
        }
        if ($statement instanceof CreateIndex) {
            $index = new IndexDefinition($statement->kind, $statement->parts, new ColumnName($statement->name), $statement->algorithm, $statement->options);
            $commands = [new AddConstraint($index)];
            foreach ($statement->alterOptions as $option) {
                assert($option instanceof AlterModifier);
                $commands[] = $option;
            }

            return [$statement->table, $commands];
        }
        $commands = [new DropElement(ElementKind::Index, new ColumnName($statement->index))];
        foreach ($statement->options as $option) {
            assert($option instanceof AlterModifier);
            $commands[] = $option;
        }

        return [$statement->table, $commands];
    }

    /**
     * Answers the declarations of the tables of the server other than one.
     *
     * @return list<Table>
     */
    public function others(Session $session, StoredTable $table): array
    {
        return array_values(array_filter($session->instance->dictionary->declarations(), static fn (Table $declaration): bool => $declaration !== $table->definition->declaration));
    }

    /**
     * Tells whether the server copies the rows of the table to change it, and refuses ALGORITHM=INPLACE, ALGORITHM=INSTANT and LOCK=NONE where it must.
     *
     * A change of a column type copies the table, and so do CONVERT TO CHARACTER SET, ORDER BY,
     * ALGORITHM=COPY, dropping the primary key, a column becoming AUTO_INCREMENT, and a column
     * becoming NOT NULL outside a strict mode. ALGORITHM=INSTANT is refused for a change of the indexes or one that copies.
     * Source: https://dev.mysql.com/doc/refman/8.4/en/innodb-online-ddl-operations.html.
     *
     * @param list<int|null> $origins
     *
     * @throws \MySqlMemory\Error\SqlError When the algorithm or lock cannot be used
     */
    public function copies(TableDefinition $old, TableDefinition $new, array $origins, TableChange $change, Context $context): bool
    {
        $retyped = false;
        $copies = $change->copies || $change->algorithm === 'copy' || ($old->primaryKey() !== null && $new->primaryKey() === null);
        foreach ($new->columns as $position => $column) {
            $origin = $origins[$position] ?? null;
            if ($origin === null) {
                continue;
            }
            $before = $old->columns[$origin];
            $retyped = $retyped || !TableRebuild::inPlace($before->domain, $column->domain);
            $copies = $copies || ($column->autoIncrement && !$before->autoIncrement) || ($before->nullable() && !$column->nullable() && !$context->modes->strict());
        }
        if ($retyped && $change->algorithm === 'inplace') {
            throw ErrorCode::AlterOperationNotSupportedReason->error('ALGORITHM=INPLACE', 'Cannot change column type INPLACE', 'ALGORITHM=COPY');
        }
        if ($retyped && $change->lock === 'none') {
            throw ErrorCode::AlterOperationNotSupportedReason->error('LOCK=NONE', 'Cannot change column type INPLACE', 'LOCK=SHARED');
        }
        $rekeyed = array_map(static fn ($key): array => [$key->name, $key->kind, $key->columns], $old->keys) !== array_map(static fn ($key): array => [$key->name, $key->kind, $key->columns], $new->keys);
        if ($change->algorithm === 'instant' && ($retyped || $copies || $rekeyed)) {
            throw ErrorCode::AlterOperationNotSupported->error('ALGORITHM=INSTANT', 'ALGORITHM=COPY/INPLACE');
        }

        return $copies || $retyped;
    }

    /**
     * Moves a table from its old name to the name its definition holds.
     */
    public function move(Session $session, StoredTable $table, string $schema, string $name): void
    {
        $dictionary = $session->instance->dictionary;
        unset($dictionary->schemas[$schema]->tables[$name]);
        $dictionary->schemas[$table->definition->schema]->tables[$table->definition->name] = $table;
    }
}
