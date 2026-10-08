<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\DataError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
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
        $chosen = $this->collation($statement, $session->settings()->release(), $context);
        $schema = $this->schema($statement, $session);
        $session->transaction->commit();
        if ($chosen !== null) {
            $schema->collation = $chosen->name;
        }

        return new Completion(1, 0, $context->diagnostics->count());
    }

    /**
     * Checks the options in order and answers the collation they choose, or null when they name
     * neither a character set nor a collation. A character set alone takes its default collation
     * in the release.
     *
     * @throws \MySqlMemory\Error\SqlError When an option is unknown, two options conflict, or the encryption is not Y or N
     */
    public function collation(AlterDatabase $statement, GrammarRelease $release, Context $context): ?Collation
    {
        $charset = null;
        $collation = null;
        foreach ($statement->options as $option) {
            if ($option instanceof DatabaseCharset && $option->charset->name !== null) {
                $charset = $this->charset($option, $charset, $context);
            }
            if ($option instanceof DatabaseCollation && $option->collation->name !== null) {
                $collation = Collation::named($option->collation->name->value);
                if ($collation === null) {
                    throw SchemaError::UnknownCollation->error($option->collation->name->value);
                }
            }
            if ($option instanceof DatabaseEncryption && !in_array(strtoupper($option->encryption->value), ['Y', 'N'], true)) {
                throw DataError::WrongValue->error('argument (should be Y or N)', $option->encryption->value);
            }
        }
        if ($charset !== null && $collation !== null && $collation->charset->name !== $charset->name) {
            throw SchemaError::CollationCharsetMismatch->error($collation->name, $charset->name);
        }

        return $collation ?? $charset?->defaultCollation($release);
    }

    /**
     * Answers the character set a CHARACTER SET option names, given the one an earlier option
     * named, and warns of utf8 as an alias of utf8mb3.
     *
     * @throws \MySqlMemory\Error\SqlError When the character set is unknown or differs from the earlier one
     */
    public function charset(DatabaseCharset $option, ?Charset $earlier, Context $context): Charset
    {
        $name = $option->charset->name->value ?? '';
        $named = Charset::named($name);
        if ($named === null) {
            throw SchemaError::UnknownCharacterSet->error($name);
        }
        if ($earlier !== null && $earlier->name !== $named->name) {
            throw SchemaError::ConflictingDeclarations->error('CHARACTER SET ', $earlier->name, 'CHARACTER SET ', $named->name);
        }
        if (strcasecmp($name, 'utf8') === 0) {
            $context->diagnostics->warning(Deprecated::Utf8Alias->code(), Deprecated::Utf8Alias->value);
        }

        return $named;
    }

    /**
     * Answers the database the statement changes: the named one, or the current one.
     *
     * @throws \MySqlMemory\Error\SqlError When no database is named or current, the name is empty, the database is a system database, or it does not exist
     */
    public function schema(AlterDatabase $statement, Session $session): Schema
    {
        $name = $statement->name->value ?? $session->variables->database;
        if ($name === '') {
            throw $statement->name === null ? QueryError::NoDatabase->error() : SchemaError::WrongDatabaseName->error($name);
        }
        if (in_array(strtolower($name), ['information_schema', 'performance_schema'], true)) {
            throw AccountError::DatabaseAccessDenied->error($session->user, explode('@', $session->variables->definer)[1] ?? '%', $name);
        }
        $schema = $session->instance->dictionary->schema($name);
        if ($schema === null) {
            throw SchemaError::SchemaMissing->error($name);
        }

        return $schema;
    }
}
