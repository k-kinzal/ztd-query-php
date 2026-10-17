<?php

declare(strict_types=1);

namespace MySqlMemory\Command;

use MySqlMemory\Error\Family\QueryError;
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
 * Temporary tables are not listed; the system tables of the release are. FULL adds the type of
 * each: BASE TABLE, VIEW or SYSTEM VIEW. LIKE matches
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
            throw QueryError::NoDatabase->error();
        }
        $schema = $session->instance->dictionary->schema($name);
        if ($schema === null) {
            throw QueryError::BadDatabase->error($name);
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
        foreach ($session->instance->dictionary->system?->catalog->tables ?? [] as $system) {
            if ($system->schema === $schema->name) {
                $rows[] = [$system->name, $system->type];
            }
        }
        usort($rows, static fn (array $left, array $right): int => strcmp($left[0], $right[0]));
        $full = $statement->listing?->full() ?? false;
        $heading = 'Tables_in_' . $name . ($statement->filter instanceof ShowLike ? ' (' . $statement->filter->pattern->value . ')' : '');
        $legacy = $session->settings()->legacy();
        $headings = [$legacy ? Show\Heading::text($heading, Field::VarString, 64, 1, 0, 'TABLE_NAME', 'TABLE_NAMES', 'TABLE_NAMES', 'information_schema') : Show\Heading::text($heading, Field::VarString, 64, 4225, 0, $heading, 'TABLES', 'tables')];
        if ($full) {
            $headings[] = $legacy ? Show\Heading::text('Table_type', Field::VarString, 64, 1, 0, 'TABLE_TYPE', 'TABLE_NAMES', 'TABLE_NAMES', 'information_schema') : Show\Heading::text('Table_type', Field::String, 11, 4481, 0, 'Table_type', 'TABLES', 'tables');
        }
        $rows = array_map(static fn (array $row): array => $full ? $row : [$row[0]], $rows);

        return $this->result($headings, $rows, $operation, $session, $context, $connection, $legacy, $heading);
    }

    /**
     * Sends the listing, retaining the dictionary enum's key flag when no sort materialization
     * is needed: a constant-empty filter or equality on the table-name key.
     *
     * @param list<Show\Heading> $headings
     * @param list<list<string>> $rows
     */
    public function result(array $headings, array $rows, Operation $operation, Session $session, Context $context, Connection $connection, bool $legacy, string $name): Reply
    {
        $statement = $operation->statement;
        assert($statement instanceof ShowTables);
        $listing = new Show\Listing($headings);
        $result = $listing->result($rows, $operation, $session, $context, $connection, $statement->filter, 0, 'utf8mb3_bin');
        $fixed = Show\FixedColumns::of($statement->filter, $operation->facts);
        if ($legacy || count($headings) !== 2 || (!$listing->constantEmpty && !isset($fixed[strtolower($name)]))) {
            return $result;
        }
        $type = $result->columns[1];
        $column = new \MySqlMemory\Result\ResultColumn($type->name, $type->type, $type->length, $type->decimals, $type->flags | \MySqlMemory\Result\ColumnFlag::MultipleKey->value, $type->charset, $type->originalName, $type->table, $type->originalTable, $type->schema);

        return new \MySqlMemory\Result\ResultSet([$result->columns[0], $column], $result->rows, $result->warnings);
    }
}
