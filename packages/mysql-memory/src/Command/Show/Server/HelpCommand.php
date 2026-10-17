<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show\Server;

use MySqlMemory\Command\Command;
use MySqlMemory\Command\Show\Help\Search;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\Help;
use SqlSemantics\Statement\Operation;

/**
 * Executes HELP: the topics and categories of the help tables that match a search string.
 *
 * The installed help data is initial server state. The search string is supplied by the
 * semantic structure; execution needs neither SQL text nor a parser tree.
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
        return (new Search($session->instance->help))->find($operation->statement->topic->value, $context)->sent($context);
    }
}
