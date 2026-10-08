<?php

declare(strict_types=1);

namespace MySqlMemory\Command\View;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Program\ProgramSource;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Dictionary\Routine;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Error\SchemaError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Plan\Views;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateView;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW CREATE VIEW: the statement that creates a view, with its query as the server stores it.
 *
 * A database that does not exist is ER_BAD_DB_ERROR, a missing view ER_NO_SUCH_TABLE and a base
 * table ER_WRONG_OBJECT. The view and the tables of its query are qualified by their database
 * when it is not the current one. A view whose tables have changed so that it no longer
 * resolves is shown with the warning ER_VIEW_INVALID. The statement column is at least 1024
 * characters long (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-view.html.
 *
 * @visibility MySqlMemory
 */
final class ShowCreateViewCommand implements Command
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
     * Writes the statement.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowCreateView);
        $name = $statement->view->name;
        $database = ProgramSource::database($name->schema, $session);
        $schema = $session->instance->dictionary->schema($database);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($database);
        }
        if ($schema->table($name->name->value) !== null) {
            throw SchemaError::WrongObject->error($database, $name->name->value, 'VIEW');
        }
        $view = $schema->views[$name->name->value] ?? null;
        if ($view === null) {
            throw QueryError::NoSuchTable->error($database, $name->name->value);
        }

        return $this->write($view, $session, $context);
    }

    /**
     * Writes the statement that creates a view, as SHOW CREATE VIEW and SHOW CREATE TABLE write it.
     */
    public function write(\MySqlMemory\Dictionary\View $view, Session $session, Context $context): Reply
    {
        try {
            Views::refresh($view, $session->instance->dictionary, $session->settings());
        } catch (SqlError $error) {
            $context->diagnostics->warning($error->error, $error->getMessage());
        }
        $current = $session->variables->database;
        $text = (new ViewText($view->created->facts, $current, $view->database))->query($view->definition) ?? $view->select;
        $create = $view->create(($view->schema === $current ? '' : Routine::quoted($view->schema) . '.') . Routine::quoted($view->name), $text);
        $flag = ColumnFlag::NotNull->value;

        return (new Listing([
            Heading::text('View', Field::VarString, 64, $flag, 31),
            Heading::text('Create View', Field::VarString, max(1024, mb_strlen($create)), $flag, 31),
            Heading::text('character_set_client', Field::VarString, 32, $flag, 31),
            Heading::text('collation_connection', Field::VarString, 32, $flag, 31),
        ]))->sent([[$view->name, $create, ...$view->charsets]], $context);
    }
}
