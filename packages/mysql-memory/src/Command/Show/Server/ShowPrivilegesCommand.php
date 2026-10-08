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
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Server\ShowPrivileges;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW PRIVILEGES: the static privileges with their context and description, then the dynamic ones.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-privileges.html.
 *
 * @visibility MySqlMemory
 */
final class ShowPrivilegesCommand implements Command
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
     * Lists the privileges.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation->statement instanceof ShowPrivileges);
        $headings = [
            Heading::text('Privilege', Field::VarString, 10, ColumnFlag::NotNull->value, 31),
            Heading::text('Context', Field::VarString, 15, ColumnFlag::NotNull->value, 31),
            Heading::text('Comment', Field::VarString, 64, ColumnFlag::NotNull->value, 31),
        ];

        return (new Listing($headings))->sent(ServerCatalog::shared()->privileges, $context);
    }
}
