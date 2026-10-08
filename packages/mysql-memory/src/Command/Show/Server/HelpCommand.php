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
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Help;
use SqlSemantics\Statement\Operation;

/**
 * Executes HELP: the topics and categories of the help tables that match a search string.
 *
 * The help tables of the server hold the text of the reference manual; the emulator has none,
 * so a search finds nothing and answers the empty list of topics and categories the server
 * answers for a string that matches none.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/help.html.
 *
 * @visibility MySqlMemory
 */
final class HelpCommand implements Command
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
     * Searches the help tables.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation->statement instanceof Help);
        $headings = [
            Heading::text('name', Field::VarString, 64, ColumnFlag::NotNull->value, 31),
            Heading::text('is_it_category', Field::VarString, 1, ColumnFlag::NotNull->value, 31),
        ];

        return (new Listing($headings))->sent([], $context);
    }
}
