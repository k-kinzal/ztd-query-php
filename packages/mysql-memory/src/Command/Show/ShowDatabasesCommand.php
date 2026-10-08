<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Command\Command;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowDatabases;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW DATABASES and SHOW SCHEMAS: the databases of the server, in name order, optionally filtered by LIKE or WHERE.
 *
 * The column is Database, read from INFORMATION_SCHEMA.SCHEMATA; LIKE names it with the
 * pattern and matches the name with regard to letter case (verified on live 8.0, 8.4 and 9.1
 * servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-databases.html.
 *
 * @visibility MySqlMemory
 */
final class ShowDatabasesCommand implements Command
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
     * Lists the databases.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowDatabases);
        $names = array_map(static fn ($schema): string => $schema->name, array_values($session->instance->dictionary->schemas));
        sort($names, SORT_STRING);
        $heading = 'Database' . ($statement->filter instanceof ShowLike ? ' (' . $statement->filter->pattern->value . ')' : '');
        $headings = [Heading::text($heading, Field::VarString, 64, 4225, 0, $heading, 'SCHEMATA', 'schemata')];

        return (new Listing($headings))->result(array_map(static fn (string $name): array => [$name], $names), $operation, $session, $context, $connection, $statement->filter, 0, 'utf8mb3_bin');
    }
}
