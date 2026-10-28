<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Database;

use MySqlMemory\Command\Command;
use MySqlMemory\Dictionary\Schema;
use MySqlMemory\Error\Family\AccountError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\Family\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Completion;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Server\Database\AlterDatabase;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCharset;
use SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseCollation;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;

/**
 * Executes ALTER DATABASE: changes the default character set and collation of a database.
 *
 * Without a name it changes the current database. The options are checked first: an unknown
 * character set or collation, two different character sets, a collation of another character
 * set are refused; utf8 is warned of as an alias of utf8mb3. Encryption is
 * already a two-case semantic setting. Then the name: an empty name is
 * ER_WRONG_DB_NAME, a system database cannot be changed, and a database that does not exist is
 * error 3503 (legacy releases report its missing db.opt path as error 1). A character set alone takes its default collation. The
 * statement commits the open transaction and affects one row. Encryption changes
 * the default for new persistent tables. READ ONLY is retained by the semantic
 * model but is not yet enforced by the emulator. Verified on a live 8.4 server.
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
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        assert($statement instanceof AlterDatabase);
        $chosen = $this->collation($statement, $session->settings()->release(), $context);
        $schema = $this->schema($statement, $session);
        $session->transaction->commit();
        if ($chosen !== null) {
            $schema->collation = $chosen->name;
            if ($session->variables->database === $schema->name) {
                $session->variables->databaseCollation($chosen);
            }
        }

        foreach ($statement->options as $option) {
            if ($option instanceof \SqlSemantics\Platform\MySql\Statement\Server\Database\DatabaseEncryption) {
                if ($option->encryption instanceof \SqlSemantics\Platform\MySql\Statement\Literal\Text) {
                    throw (new \MySqlMemory\Session\Problem\Errors())->error(new \SqlSemantics\Platform\MySql\Statement\Server\Database\Problem\InvalidEncryption($option->encryption), $session);
                }
                $schema->encryption = $option->encryption;
            }
        }

        return new Completion(1, 0, $context->diagnostics->raised());
    }

    /**
     * Checks the options in order and answers the collation they choose, or null when they name
     * neither a character set nor a collation. A character set alone takes its default collation
     * in the release.
     *
     * @throws \MySqlMemory\Error\SqlError When an option is unknown or two options conflict
     */
    public function collation(AlterDatabase $statement, GrammarRelease $release, Context $context): ?Collation
    {
        $charset = null;
        $collation = null;
        foreach ($statement->options as $option) {
            if ($option instanceof DatabaseCharset) {
                $charset = $option->charset->name === null ? Charset::known((string) $context->variables->read('character_set_server')) : $this->charset($option, $charset, $context);
            }
            if ($option instanceof DatabaseCollation) {
                $name = $option->collation->name->value ?? (string) $context->variables->read('collation_server');
                $collation = Collation::named($name);
                if ($collation === null) {
                    throw SchemaError::UnknownCollation->error((new \MySqlMemory\Error\DiagnosticText())->bytes(explode("\0", $name, 2)[0], 64, in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true)));
                }
            }
        }
        if ($charset !== null && $collation !== null && $collation->charset->name !== $charset->name) {
            throw SchemaError::CollationCharsetMismatch->error($collation->name, $charset->name);
        }

        return $collation ?? $charset?->defaultCollation($release);
    }

    /**
     * Answers the character set a CHARACTER SET option names, given the one an earlier option
     * named. Semantic derivation supplies charset deprecation notices.
     *
     * @throws \MySqlMemory\Error\SqlError When the character set is unknown or differs from the earlier one
     */
    public function charset(DatabaseCharset $option, ?Charset $earlier, Context $context): Charset
    {
        $name = $option->charset->name->value ?? '';
        $named = Charset::named($name);
        if ($named === null) {
            throw (new \MySqlMemory\Error\TypeErrors())->charset($name, in_array($context->modes->release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true));
        }
        if ($earlier !== null && $earlier->name !== $named->name) {
            throw SchemaError::ConflictingDeclarations->error('CHARACTER SET ', $earlier->name, 'CHARACTER SET ', $named->name);
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
            throw AccountError::DatabaseAccessDenied->error($session->user, $session->variables->definer->host, $name);
        }
        $schema = $session->instance->dictionary->schema($name);
        if ($schema === null) {
            throw $session->settings()->legacy()
                ? \MySqlMemory\Error\Family\AdministrationError::FileCreate->error('./' . (new FileName())->encoded($name) . '/db.opt', 2, 'No such file or directory')
                : SchemaError::SchemaMissing->error($name);
        }

        return $schema;
    }
}
