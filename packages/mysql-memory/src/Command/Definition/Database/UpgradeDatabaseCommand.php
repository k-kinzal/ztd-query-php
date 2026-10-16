<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Database;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Definition\RenameTableCommand;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Dictionary\View;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Database\UpgradeDatabaseName;
use SqlSemantics\Statement\Declaration\Table;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
use SqlSemantics\Statement\Operation;

/**
 * Upgrades a legacy database name by removing its #mysql50# prefix.
 *
 * Tables, rows, views and triggers move. Stored routines and events are dropped;
 * view queries and trigger execution databases retain their old references.
 * The selected database follows the move. The statement affects no rows.
 * Verified against isolated MySQL 5.6.51 and 5.7.44 servers.
 * Source: https://docs.oracle.com/cd/E17952_01/mysql-5.7-en/alter-database.html.
 *
 * @visibility MySqlMemory
 */
final class UpgradeDatabaseCommand implements Command
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
     * Moves the database and its table data to the upgraded name.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof UpgradeDatabaseName);
        $source = $this->source($statement->name->identifier()->value, $session);
        $session->transaction->commit();
        $this->move($source, $session, $context, $connection);

        return new Completion(0, 0, $context->diagnostics->raised());
    }

    /**
     * Finds the legacy source before checking its destination.
     * An ASCII name needing no directory encoding also names its existing directory with the prefix.
     *
     * @throws \MySqlMemory\Error\SqlError When the name cannot be upgraded
     */
    public function source(string $name, Session $session): Schema
    {
        if (!str_starts_with($name, '#mysql50#')) {
            throw StatementError::WrongUsage->error('ALTER DATABASE UPGRADE DATA DIRECTORY NAME', 'name');
        }
        if ($name === '#mysql50#') {
            throw SchemaError::WrongDatabaseName->error($name);
        }
        $dictionary = $session->instance->dictionary;
        $destination = substr($name, 9);
        $source = $dictionary->schema($name) ?? (preg_match('/\A[A-Za-z0-9_]+\z/', $destination) === 1 ? $dictionary->schema($destination) : null);
        if ($source === null) {
            throw QueryError::BadDatabase->error($name);
        }
        if ($dictionary->schema($destination) !== null) {
            throw SchemaError::DatabaseExists->error($destination);
        }

        return $source;
    }

    /**
     * Rebuilds declarations before publishing the renamed tables.
     *
     * @throws \MySqlMemory\Error\SqlError When a table cannot be declared again
     */
    public function move(Schema $source, Session $session, Context $context, Connection $connection): void
    {
        $dictionary = $session->instance->dictionary;
        $target = new Schema(substr($source->name, 9), $source->collation);
        $dictionary->schemas[$target->name] = $target;
        $definitions = [];
        $prepared = false;
        try {
            foreach ($source->tables as $name => $table) {
                $definitions[$name] = (new RenameTableCommand())->renamed($session, $context, $connection, $table, $target->name, $name);
            }
            foreach ($source->views as $name => $view) {
                $target->views[$name] = $this->view($view, $target->name);
            }
            $prepared = true;
        } finally {
            if (!$prepared) {
                unset($dictionary->schemas[$target->name]);
            }
        }
        foreach ($source->tables as $name => $table) {
            $table->definition = $definitions[$name];
            $target->tables[$name] = $table;
        }
        $target->triggers = $source->triggers;
        unset($dictionary->schemas[$source->name]);
        foreach ($target->tables as $name => $table) {
            $dictionary->retarget($source->name, $name, $target->name, $name);
        }
        $dictionary->routineGeneration++;
        $dictionary->cache->close($source->name);
        if ($session->variables->database === $source->name) {
            $session->variables->database = $target->name;
        }
    }

    /**
     * Moves the view's declaration while retaining its original query references.
     */
    public function view(View $view, string $schema): View
    {
        $original = $view->declaration;
        $declaration = new Table(new QualifiedName(new Name($view->name), new Name($schema)), $original->profile, $original->columns, $original->implicit, $original->complete, $original->kind, $original->keys, $original->partitions);
        $renamed = new View($schema, $view->name, $view->definer, $view->algorithm, $view->security, $view->check, $view->database, $view->created, $view->definition, $declaration, $view->tables, $view->charsets);
        $renamed->positions = $view->positions;
        $renamed->hints = $view->hints;

        return $renamed;
    }
}
