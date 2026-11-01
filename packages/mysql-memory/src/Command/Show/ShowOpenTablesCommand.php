<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Show;

use MySqlMemory\Command\Command;
use MySqlMemory\Evaluation\Compile\Connection;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Result\ColumnFlag;
use MySqlMemory\Result\Reply;
use MySqlMemory\Session\Session;
use Override;
use SqlSemantics\Platform\MySql\Statement\Statement as MySqlStatement;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;
use SqlSemantics\Platform\MySql\Statement\Utility\Show\Schema\ShowOpenTables;

/**
 * Executes SHOW OPEN TABLES over the retained table handles and their session uses.
 *
 * Table definitions and open handles have separate lifetimes. FROM names the database
 * whose tables are listed; one that does not exist lists none. LIKE matches the table names with
 * regard to case.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/show-open-tables.html.
 *
 * @visibility MySqlMemory
 */
final class ShowOpenTablesCommand implements Command
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
     * Lists the open tables.
     */
    #[Override]
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof MySqlStatement);
        $statement = $operation;
        assert($statement instanceof ShowOpenTables);
        $rows = [];
        foreach ($session->instance->dictionary->cache->names() as [$schema, $table]) {
            if ($statement->database !== null && $schema !== $statement->database->value) {
                continue;
            }
            $rows[] = [$schema, $table, (new \MySqlMemory\Session\Access\OpenedTables())->uses($schema, $table, $session), 0];
        }
        return (new Listing($this->headings($session->settings()->legacy())))->result($rows, $operation, $session, $context, $connection, $statement->filter, 1, 'utf8mb4_bin');
    }
    /**
     * @return list<Heading> The open-table output domains and column identities
     */
    public function headings(bool $legacy): array
    {
        $table = 'OPEN_TABLES';
        $schema = 'information_schema';
        $oldWidths = $legacy;
        return [
            Heading::text('Database', Field::VarString, 64, ColumnFlag::NotNull->value, 0, 'Database', $table, $table, $schema),
            Heading::text('Table', Field::VarString, 64, ColumnFlag::NotNull->value, 0, 'Table', $table, $table, $schema),
            new Heading('In_use', Field::LongLong, $oldWidths ? 1 : 2, ColumnFlag::NotNull->value | ColumnFlag::Numeric->value, 0, false, 'In_use', $table, $table, $schema),
            new Heading('Name_locked', Field::LongLong, $oldWidths ? 4 : 5, ColumnFlag::NotNull->value | ColumnFlag::Numeric->value, 0, false, 'Name_locked', $table, $table, $schema),
        ];
    }
}
