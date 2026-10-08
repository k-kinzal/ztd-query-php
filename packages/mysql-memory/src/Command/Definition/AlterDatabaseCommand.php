<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Notice\Deprecated;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Statement\Operation;

/**
 * Executes ALTER DATABASE: changes the default character set and collation of a database.
 *
 * Without a name it changes the current database. The options are checked first: an unknown
 * character set or collation, two different character sets, a collation of another character
 * set, and an ENCRYPTION other than Y or N are refused; utf8 is warned of as an alias of utf8mb3. Then the name: an empty name is
 * ER_WRONG_DB_NAME, a system database cannot be changed, and a database that does not exist is
 * error 3503. A character set alone takes its default collation. The
 * statement commits the open transaction and affects one row. READ ONLY and ENCRYPTION are
 * accepted and have no effect on the emulator's databases. Every rule was verified on a live 8.4
 * server.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/alter-database.html.
 *
 * @visibility MySqlMemory
 */
final class AlterDatabaseCommand implements Command
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
     * Changes the database.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof AlterDatabase);
        $release = $session->settings()->release();
        $charset = null;
        $collation = null;
        foreach ($statement->options as $option) {
            if ($option instanceof DatabaseCharset && $option->charset->name !== null) {
                $named = Charset::named($option->charset->name->value);
                if ($named === null) {
                    throw ErrorCode::UnknownCharacterSet->error($option->charset->name->value);
                }
                if ($charset !== null && $charset->name !== $named->name) {
                    throw ErrorCode::ConflictingDeclarations->error('CHARACTER SET ', $charset->name, 'CHARACTER SET ', $named->name);
                }
                $charset = $named;
                if (strcasecmp($option->charset->name->value, 'utf8') === 0) {
                    $context->diagnostics->warning(Deprecated::Utf8Alias->code(), Deprecated::Utf8Alias->value);
                }
            }
            if ($option instanceof DatabaseCollation && $option->collation->name !== null) {
                $collation = Collation::named($option->collation->name->value);
                if ($collation === null) {
                    throw ErrorCode::UnknownCollation->error($option->collation->name->value);
                }
            }
            if ($option instanceof DatabaseEncryption && !in_array(strtoupper($option->encryption->value), ['Y', 'N'], true)) {
                throw ErrorCode::WrongValue->error('argument (should be Y or N)', $option->encryption->value);
            }
        }
        if ($charset !== null && $collation !== null && $collation->charset->name !== $charset->name) {
            throw ErrorCode::CollationCharsetMismatch->error($collation->name, $charset->name);
        }
        $name = $statement->name->value ?? $session->variables->database;
        if ($name === '') {
            throw $statement->name === null ? ErrorCode::NoDatabase->error() : ErrorCode::WrongDatabaseName->error($name);
        }
        if (in_array(strtolower($name), ['information_schema', 'performance_schema'], true)) {
            throw ErrorCode::DatabaseAccessDenied->error($session->user, explode('@', $session->variables->definer)[1] ?? '%', $name);
        }
        $schema = $session->instance->dictionary->schema($name);
        if ($schema === null) {
            throw ErrorCode::SchemaMissing->error($name);
        }
        $session->transaction->commit();
        $chosen = $collation ?? $charset?->defaultCollation($release);
        if ($chosen !== null) {
            $schema->collation = $chosen->name;
        }

        return new Completion(1, 0, $context->diagnostics->count());
    }
}
