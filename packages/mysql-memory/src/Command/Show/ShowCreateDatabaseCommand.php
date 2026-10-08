<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Command\Command;
use MySqlMemory\Error\QueryError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowCreateDatabase;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW CREATE DATABASE: the statement that creates a database with its default character set and collation.
 *
 * The collation is written unless it is the default collation of a character set other than
 * utf8mb4; IF NOT EXISTS is written in a versioned comment. INFORMATION_SCHEMA is a utf8mb3
 * database (verified on a live 8.4 server). MySQL 5.6 and 5.7 omit the collation whenever it is the
 * default of its character set, utf8mb4 included, and write no ENCRYPTION comment (verified on a
 * live 5.7.44 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-create-database.html.
 *
 * @visibility MySqlMemory
 */
final class ShowCreateDatabaseCommand implements Command
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
        assert($statement instanceof ShowCreateDatabase);
        $name = $statement->name->value;
        $schema = $session->instance->dictionary->schema($name);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($name);
        }
        $collation = $name === 'information_schema' ? Collation::known('utf8mb3_general_ci') : (Collation::named($schema->collation) ?? Collation::known('utf8mb4_0900_ai_ci'));
        $charset = $collation->charset;
        $release = $session->settings()->release();
        $legacy = $release === GrammarRelease::MySql5651 || $release === GrammarRelease::MySql5744;
        $default = ($legacy || $charset->name !== 'utf8mb4') && $charset->defaultCollation($legacy ? $release : GrammarRelease::MySql847)->name === $collation->name;
        $text = 'CREATE DATABASE ' . ($statement->ifNotExists ? '/*!32312 IF NOT EXISTS*/ ' : '') . '`' . str_replace('`', '``', $name) . '`'
            . ' /*!40100 DEFAULT CHARACTER SET ' . $charset->name . ($default ? '' : ' COLLATE ' . $collation->name) . ' */' . ($legacy ? '' : " /*!80016 DEFAULT ENCRYPTION='N' */");
        $headings = [
            Heading::text('Database', Field::VarString, 64, ColumnFlag::NotNull->value, 31),
            Heading::text('Create Database', Field::VarString, 1024, ColumnFlag::NotNull->value, 31),
        ];

        return (new Listing($headings))->sent([[$name, $text]], $context);
    }
}
