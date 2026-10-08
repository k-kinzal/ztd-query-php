<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowTables;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\ShowLike;
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW TABLES: the tables and views of a database, in name order, optionally filtered by LIKE or WHERE.
 *
 * Temporary tables are not listed. FULL adds the type of each: BASE TABLE or VIEW. LIKE matches
 * the name as it is written, and names the column with the pattern (verified on a live 8.4
 * server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-tables.html.
 *
 * @visibility MySqlMemory
 */
final class ShowTablesCommand implements Command
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
     * Lists the tables.
     */
    #[Override]
    public function execute(Operation $operation, Session $session, Context $context, Connection $connection): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowTables);
        $name = $statement->database->value ?? $session->variables->database;
        if ($name === '') {
            throw ErrorCode::NoDatabase->error();
        }
        $schema = $session->instance->dictionary->schema($name);
        if ($schema === null) {
            throw ErrorCode::BadDatabase->error($name);
        }
        $rows = [];
        foreach ($schema->tables as $stored) {
            if (!$stored->definition->temporary) {
                $rows[] = [$stored->definition->name, 'BASE TABLE'];
            }
        }
        foreach ($schema->views as $view) {
            $rows[] = [$view->name, 'VIEW'];
        }
        usort($rows, static fn (array $left, array $right): int => strcmp($left[0], $right[0]));
        $full = $statement->listing?->full() ?? false;
        $heading = 'Tables_in_' . $name . ($statement->filter instanceof ShowLike ? ' (' . $statement->filter->pattern->value . ')' : '');
        $headings = [Show\Heading::text($heading, Field::VarString, 64, 4225, 0, $heading, 'TABLES', 'tables')];
        if ($full) {
            $headings[] = Show\Heading::text('Table_type', Field::String, 11, 4481, 0, 'Table_type', 'TABLES', 'tables');
        }
        $rows = array_map(static fn (array $row): array => $full ? $row : [$row[0]], $rows);

        return (new Show\Listing($headings))->result($rows, $operation, $session, $context, $connection, $statement->filter, 0, 'utf8mb3_bin');
    }
}
