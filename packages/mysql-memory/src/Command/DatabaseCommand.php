<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Error\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Server\Database\CreateDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DropDatabase;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\UseDatabase;
use SqlSemantics\Statement\Operation;

/**
 * Executes CREATE DATABASE, DROP DATABASE and USE.
 *
 * CREATE DATABASE IF NOT EXISTS of an existing database and DROP DATABASE IF EXISTS of a missing
 * one succeed with a note; DROP DATABASE answers the number of tables it dropped.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-database.html.
 *
 * @visibility MySqlMemory
 */
final class DatabaseCommand implements Command
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
     * Executes the statement.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        $dictionary = $session->instance->dictionary;
        if ($statement instanceof UseDatabase) {
            $session->use($statement->database->value);

            return new Completion();
        }
        if ($statement instanceof CreateDatabase) {
            $name = $statement->name->value;
            if ($dictionary->schema($name) !== null) {
                if (!$statement->ifNotExists) {
                    throw SchemaError::DatabaseExists->error($name);
                }
                $context->diagnostics->note(SchemaError::DatabaseExists, SchemaError::DatabaseExists->message($name));

                return new Completion(0, 0, 1);
            }
            $dictionary->schemas[$name] = new Schema($name, $this->collation($statement, $session));

            return new Completion(1);
        }
        assert($statement instanceof DropDatabase);
        $name = $statement->name->value;
        $schema = $dictionary->schema($name);
        if ($schema === null) {
            if (!$statement->ifExists) {
                throw SchemaError::DatabaseMissing->error($name);
            }
            $context->diagnostics->note(SchemaError::DatabaseMissing, SchemaError::DatabaseMissing->message($name));

            return new Completion(0, 0, 1);
        }
        unset($dictionary->schemas[$name]);
        if ($session->variables->database === $name) {
            $session->variables->database = '';
        }

        return new Completion(count($schema->tables));
    }

    /**
     * Answers the default collation of a new database: the collation it names, else the default
     * collation of the character set it names, else collation_server (utf8mb4_0900_ai_ci in 8.0
     * and later, latin1_swedish_ci in 5.6 and 5.7).
     * Source: https://dev.mysql.com/doc/refman/8.4/en/charset-database.html.
     */
    public function collation(CreateDatabase $statement, Session $session): string
    {
        $server = $session->variables->read('collation_server');
        $collation = is_string($server) && \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::named($server) !== null ? $server : 'utf8mb4_0900_ai_ci';
        foreach ($statement->options as $option) {
            if ($option instanceof DatabaseCharset && $option->charset->name !== null) {
                $collation = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset::named($option->charset->name->value)?->defaultCollation($session->settings()->release())->name ?? $collation;
            }
        }
        foreach ($statement->options as $option) {
            if ($option instanceof DatabaseCollation && $option->collation->name !== null) {
                $collation = strtolower($option->collation->name->value);
            }
        }

        return $collation;
    }
}
