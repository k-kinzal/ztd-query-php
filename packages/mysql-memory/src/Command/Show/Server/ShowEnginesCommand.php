<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Server;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Error\SchemaError;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineCatalog;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineLogs;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineMutex;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowEngineStatus;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW ENGINES and SHOW ENGINE ... {STATUS | MUTEX | LOGS}.
 *
 * SHOW ENGINES lists the storage engines of the server and what they support. SHOW ENGINE names
 * an engine, by its name or an alias, or ALL; an engine the server does not have or has
 * disabled is ER_UNKNOWN_STORAGE_ENGINE, named as written. The status, mutexes and logs an
 * engine reports describe the running server, its threads, waits and files; the emulator has
 * none of them, so it reports no row.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-engines.html,
 * https://dev.mysql.com/doc/refman/8.4/en/show-engine.html.
 *
 * @visibility MySqlMemory
 */
final class ShowEnginesCommand implements Command
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
     * Lists the engines, or what an engine reports.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowEngineCatalog || $statement instanceof ShowEngineLogs || $statement instanceof ShowEngineMutex || $statement instanceof ShowEngineStatus);
        if ($statement instanceof ShowEngineCatalog) {
            return (new Listing($this->catalogHeadings()))->sent(ServerCatalog::shared()->engines, $context);
        }
        if ($statement->engine !== null && ServerCatalog::shared()->engine($statement->engine->value) === null) {
            throw SchemaError::UnknownStorageEngine->error($statement->engine->value);
        }
        $headings = [
            Heading::text('Type', Field::VarString, 10, ColumnFlag::NotNull->value, 31),
            Heading::text('Name', Field::VarString, 512, ColumnFlag::NotNull->value, 31),
            Heading::text('Status', Field::VarString, 10, ColumnFlag::NotNull->value, 31),
        ];

        return (new Listing($headings))->sent([], $context);
    }

    /**
     * Answers the columns of SHOW ENGINES.
     *
     * @return list<Heading>
     */
    public function catalogHeadings(): array
    {
        $table = 'ENGINES';
        $schema = 'information_schema';

        return [
            Heading::text('Engine', Field::VarString, 64, ColumnFlag::NotNull->value, 0, 'ENGINE', $table, $table, $schema),
            Heading::text('Support', Field::VarString, 8, ColumnFlag::NotNull->value, 0, 'SUPPORT', $table, $table, $schema),
            Heading::text('Comment', Field::VarString, 80, ColumnFlag::NotNull->value, 0, 'COMMENT', $table, $table, $schema),
            Heading::text('Transactions', Field::VarString, 3, 0, 0, 'TRANSACTIONS', $table, $table, $schema),
            Heading::text('XA', Field::VarString, 3, 0, 0, 'XA', $table, $table, $schema),
            Heading::text('Savepoints', Field::VarString, 3, 0, 0, 'SAVEPOINTS', $table, $table, $schema),
        ];
    }
}
