<?php

declare(strict_types=1);

namespace MySqlMemory\System\Performance;

use MySqlMemory\Command\Show\Server\ShowVariablesCommand;
use MySqlMemory\Error\Family\StatementError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Session\Session;
use MySqlMemory\System\Reading;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTables;

/**
 * The rows the variables and status tables of the Performance Schema share: a name and a value, as SHOW VARIABLES and SHOW STATUS list them.
 *
 * MySQL 5.6 lists them in INFORMATION_SCHEMA, the names in upper case; 5.7 refuses to read
 * those tables while show_compatibility_56 is OFF, its default (verified on live 5.6.51 and
 * 5.7.44 servers).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/performance-schema-system-variable-tables.html,
 * https://dev.mysql.com/doc/refman/8.4/en/performance-schema-status-variable-tables.html.
 *
 * @visibility MySqlMemory
 */
final class Variables
{
    /**
     * Answers the rows of a table of names and values.
     *
     * @param list<array{string, string}> $values The name and value of each variable
     *
     * @return list<array<string, int|float|string|null>>
     *
     * @throws SqlError When MySQL 5.7 reads the table from INFORMATION_SCHEMA
     */
    public static function rows(array $values, Reading $reading): array
    {
        $legacy = SystemTables::key($reading->table->schema, $reading->table->name) === SystemTables::key('information_schema', $reading->table->name);
        if ($legacy && $reading->release === GrammarRelease::MySql5744) {
            throw StatementError::FeatureDisabledSeeDoc->error('INFORMATION_SCHEMA.' . $reading->table->name, 'show_compatibility_56');
        }
        if ($legacy) {
            usort($values, static fn (array $left, array $right): int => strcasecmp($left[0], $right[0]));
        }

        return array_map(static fn (array $value): array => ['VARIABLE_NAME' => $legacy ? strtoupper($value[0]) : $value[0], 'VARIABLE_VALUE' => $value[1]], $values);
    }

    /**
     * Answers the name and value of each system variable of a session or of the global scope.
     *
     * @param bool $threaded Whether to answer only the variables that have a session value
     *
     * @return list<array{string, string}>
     */
    public static function system(Session $session, bool $global, bool $threaded, Reading $reading): array
    {
        $values = (new ShowVariablesCommand())->variables($session, $global, $reading->connection->context);
        if (!$threaded) {
            return array_map(static fn (array $row): array => [$row[0] ?? '', $row[1] ?? ''], $values);
        }
        $rows = [];
        foreach ($values as $row) {
            $name = $row[0] ?? '';
            if ($session->variables->catalog->find($name)?->reach !== \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Reach::Global) {
                $rows[] = [$name, $row[1] ?? ''];
            }
        }

        return $rows;
    }

    /**
     * Answers the thread id of a session: its connection id plus the threads a server of the release starts before its first client.
     */
    public static function thread(Session $session, Reading $reading): int
    {
        return $session->id + (new \MySqlMemory\Evaluation\Function\Server\Performance())->offset($reading->release);
    }
}
