<?php

declare(strict_types=1);

namespace Tests\Unit\Session\State;

use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use MySqlMemory\Session\State\StatementCounters;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(StatementCounters::class)]
#[Small]
final class StatementCountersTest extends TestCase
{
    public function testReceivedCountsRequestsThatCannotBeParsed(): void
    {
        $session = (new Instance())->connect();
        $session->run('SELEC 1');
        $result = $session->query("SHOW STATUS WHERE Variable_name IN ('Questions','Queries','Com_select','Com_show_status')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['Com_select', '0'], ['Com_show_status', '1'], ['Queries', '2'], ['Questions', '2']], $result->rows);
    }

    public function testQueryIncludesProgramInstructionsWithoutAddingClientQuestions(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->query('CREATE PROCEDURE p() BEGIN DECLARE a INT DEFAULT 0; WHILE a<3 DO SET a=a+1; END WHILE; END');
        $before = $session->instance->registry->status->read('Queries');
        $session->query('CALL p()');

        self::assertSame(9, $session->instance->registry->status->read('Queries') - $before);
        self::assertSame(2, $session->instance->registry->status->read('Questions', $session->id));
        self::assertSame(0, $session->instance->registry->status->read('Com_set_option', $session->id));
    }

    public function testEvaluatedCountsResolvingErrorsButNotEarlyParsingErrors(): void
    {
        $session = (new Instance(databases: ['d']))->connect(database: 'd');
        $session->run('SELECT * FROM missing');
        $session->run('SET nonexistent_status_option=1');

        self::assertSame(1, $session->instance->registry->status->read('Com_select', $session->id));
        self::assertSame(0, $session->instance->registry->status->read('Com_set_option', $session->id));
    }

    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function providerFailedCommands(): iterable
    {
        foreach (['5.6.51', '5.7.44', '8.0.44', '8.4.7'] as $version) {
            foreach ([
                ['KILL 0', 'Com_kill', 1],
                ['KILL ROW(1,2)', 'Com_kill', 1],
                ['KILL unknown_column', 'Com_kill', 1],
                ['KILL ABS()', 'Com_kill', 0],
                ['KILL (SELECT 0 FROM absent)', 'Com_kill', 1],
                ['KILL absent_function()', 'Com_kill', 1],
                ['ALTER DATABASE ordinary UPGRADE DATA DIRECTORY NAME', 'Com_alter_db_upgrade', str_starts_with($version, '5.') ? 1 : 0],
                ['SHOW COLUMNS FROM absent', 'Com_show_fields', 1],
                ['DESCRIBE absent', 'Com_show_fields', 1],
                ['SHOW KEYS FROM absent', 'Com_show_keys', 1],
                ['SHOW TABLES FROM absent', 'Com_show_tables', 1],
                ['SHOW TABLE STATUS FROM absent', 'Com_show_table_status', 1],
                ['SHOW CREATE TABLE absent', 'Com_show_create_table', 1],
            ] as [$sql, $counter, $count]) {
                yield $version . ' ' . $sql => [$version, $sql, $counter, $count];
            }
        }
    }

    #[DataProvider('providerFailedCommands')]
    public function testEvaluatedCountsCommandsBeforeExecutionOperandAndTableFailures(string $version, string $sql, string $counter, int $count): void
    {
        $session = (new Instance($version, databases: ['test']))->connect(database: 'test');
        $session->run($sql);

        self::assertSame($count, $session->instance->registry->status->read($counter, $session->id));
        self::assertSame(1, $session->instance->registry->status->read('Questions', $session->id));
    }

    public function testEvaluatedDoesNotCountTheCommandRejectedDuringPrepare(): void
    {
        $session = (new Instance(databases: ['test']))->connect(database: 'test');
        $session->run("PREPARE s FROM 'SHOW COLUMNS FROM absent'");

        self::assertSame(1, $session->instance->registry->status->read('Com_prepare_sql', $session->id));
        self::assertSame(0, $session->instance->registry->status->read('Com_show_fields', $session->id));
        self::assertSame([['Error', 1146, "Table 'test.absent' doesn't exist"]], $session->diagnostics->conditions);
    }

    public function testCommandRetainsGlobalCountsAcrossFlushDisconnectAndReset(): void
    {
        $instance = new Instance();
        $session = $instance->connect();
        $other = $instance->connect();
        $session->query('SELECT 1; SELECT 2; FLUSH STATUS');
        $other->query('SELECT 3');
        $other->close();

        self::assertSame(0, $instance->registry->status->read('Com_select', $session->id));
        self::assertSame(3, $instance->registry->status->read('Com_select'));
        $instance->restart();
        self::assertSame(0, $instance->registry->status->read('Com_select'));
    }
}
