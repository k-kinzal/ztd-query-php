<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Server;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Heading;
use MySqlMemory\Command\Show\Listing;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowPlugins;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW PLUGINS: the plugins of the server, with their status, type, library and license.
 *
 * The rows are read from INFORMATION_SCHEMA.PLUGINS, which the column metadata names.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-plugins.html.
 *
 * @visibility MySqlMemory
 */
final class ShowPluginsCommand implements Command
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
     * Lists the plugins.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation->statement instanceof ShowPlugins);
        $table = 'PLUGINS';
        $schema = 'information_schema';
        $headings = [
            Heading::text('Name', Field::VarString, 64, ColumnFlag::NotNull->value, 0, 'PLUGIN_NAME', $table, $table, $schema),
            Heading::text('Status', Field::VarString, 10, ColumnFlag::NotNull->value, 0, 'PLUGIN_STATUS', $table, $table, $schema),
            Heading::text('Type', Field::VarString, 80, ColumnFlag::NotNull->value, 0, 'PLUGIN_TYPE', $table, $table, $schema),
            Heading::text('Library', Field::VarString, 64, 0, 0, 'PLUGIN_LIBRARY', $table, $table, $schema),
            Heading::text('License', Field::VarString, 80, 0, 0, 'PLUGIN_LICENSE', $table, $table, $schema),
        ];

        return (new Listing($headings))->sent(ServerCatalog::shared()->plugins, $context);
    }
}
