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
use SqlSemantics\Statement\Operation;

/**
 * Executes SHOW DATABASES and SHOW SCHEMAS: the databases of the server, in name order, optionally filtered by LIKE or WHERE.
 *
 * The column is Database, read from INFORMATION_SCHEMA.SCHEMATA; LIKE names it with the
 * pattern and matches the name with regard to letter case. MySQL 5.6 and 5.7 list
 * INFORMATION_SCHEMA first (verified on live 5.6.51, 5.7.44, 8.0, 8.4 and 9.1 servers).
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
    public function execute(\SqlSemantics\Statement\AnalyzedStatement $operation, Session $session, Context $context, Connection $connection): Reply
    {
        assert($operation instanceof Operation);
        $statement = $operation->statement;
        assert($statement instanceof ShowDatabases);
        $names = array_map(static fn ($schema): string => $schema->name, array_values($session->instance->dictionary->schemas));
        sort($names, SORT_STRING);
        if ($session->settings()->legacy()) {
            $names = ['information_schema', ...array_values(array_diff($names, ['information_schema']))];
        }
        $heading = $operation->field(0)->name;
        \SqlSemantics\Diagnostic\Check::invariant($heading !== null, 'A database inspection has a resolved column name.');
        $heading = $heading->value;
        $legacy = $session->settings()->legacy();
        $headings = [Heading::text($heading, Field::VarString, 64, $legacy ? 1 : 4225, 0, $legacy ? 'SCHEMA_NAME' : $heading, 'SCHEMATA', $legacy ? 'SCHEMATA' : 'schemata', $legacy ? 'information_schema' : '')];

        $listing = new Listing($headings);
        $result = $listing->result(array_map(static fn (string $name): array => [$name], $names), $operation, $session, $context, $connection, $statement->filter, 0, 'utf8mb3_bin');
        $fixed = FixedColumns::of($statement->filter, $operation->facts);
        if (!$legacy && ($listing->constantEmpty || isset($fixed['database']))) {
            $headings = [Heading::text($heading, Field::VarString, 64, 20609, 0, $heading, 'SCHEMATA', 'sch', 'information_schema')];
            return new \MySqlMemory\Result\ResultSet((new Listing($headings))->sent([], $context)->columns, $result->rows, $result->warnings);
        }
        return $result;
    }
}
