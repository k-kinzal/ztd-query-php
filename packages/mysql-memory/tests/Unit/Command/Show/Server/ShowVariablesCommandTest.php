<?php

declare(strict_types=1);

namespace Tests\Unit\Command\Show\Server;

use MySqlMemory\Command\Show\Server\ShowVariablesCommand;
use MySqlMemory\Evaluation\Context;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\SqlModes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(ShowVariablesCommand::class)]
#[Small]
final class ShowVariablesCommandTest extends TestCase
{
    public function testClearsDiagnosticsAnswersTrue(): void
    {
        self::assertTrue((new ShowVariablesCommand())->clearsDiagnostics());
    }

    public function testExecuteListsTheValuesOfAScope(): void
    {
        $session = (new Instance())->connect();
        $session->query("SET SESSION sql_mode = ''");

        $local = $session->query("SHOW VARIABLES LIKE 'sql_mode'")[0];
        $global = $session->query("SHOW GLOBAL VARIABLES LIKE 'sql_mode'")[0];

        self::assertInstanceOf(ResultSet::class, $local);
        self::assertInstanceOf(ResultSet::class, $global);
        self::assertSame([['sql_mode', '']], $local->rows);
        self::assertSame([['sql_mode', 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION']], $global->rows);
        self::assertSame(['session_variables', 'performance_schema', 256], [$local->columns[0]->table, $local->columns[0]->schema, $local->columns[0]->length]);
    }

    public function testExecuteListsNoStatusVariable(): void
    {
        $result = (new Instance())->connect()->query("SHOW GLOBAL STATUS LIKE 'a''b'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([], $result->rows);
        self::assertSame('global_status', $result->columns[0]->table);
    }

    public function testVariablesLeavesSessionOnlyVariablesOutOfTheGlobalScope(): void
    {
        $session = (new Instance())->connect();
        $context = new Context(new SqlModes([]), $session->diagnostics, $session->variables, 1700000000.5);

        $global = array_column((new ShowVariablesCommand())->variables($session, true, $context), 1, 0);
        $local = array_column((new ShowVariablesCommand())->variables($session, false, $context), 1, 0);

        self::assertArrayNotHasKey('timestamp', $global);
        self::assertSame(['1700000000.500000', (string) $session->id, 'ON'], [$local['timestamp'], $local['pseudo_thread_id'], $local['autocommit']]);
    }

    public function testStatusListsTheStatusVariablesWithTheStatementCounters(): void
    {
        $s = (new Instance())->connect();

        self::assertSame([['Com_select', '0'], ['Threads_connected', '1']], array_values(array_filter((new ShowVariablesCommand())->status($s, true), static fn (array $row): bool => in_array($row[0], ['Com_select', 'Threads_connected'], true))));
        $read1 = $s->query("SHOW SESSION STATUS LIKE 'Com_select'")[0];
        self::assertInstanceOf(ResultSet::class, $read1);
        self::assertSame([['Com_select', '0']], $read1->rows);
    }

    public function testVariablesWritesTheDigitsOfAnUnsignedValueBeyondTheSignedRange(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SHOW VARIABLES LIKE 'sql_select_limit'")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['sql_select_limit', '18446744073709551615']], $result->rows);
    }
}
