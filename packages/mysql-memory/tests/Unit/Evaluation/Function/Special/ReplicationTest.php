<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Function\Special;

use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Function\Special\Replication;
use MySqlMemory\Instance;
use MySqlMemory\Result\ResultSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

#[CoversClass(Replication::class)]
#[Small]
final class ReplicationTest extends TestCase
{
    public function testRoutinesNamesTheReplicationFunctions(): void
    {
        $names = array_map(static fn ($routine): string => $routine->name, (new Replication())->routines());

        self::assertSame(['GTID_SUBSET', 'GTID_SUBTRACT', 'WAIT_FOR_EXECUTED_GTID_SET', 'SOURCE_POS_WAIT', 'MASTER_POS_WAIT', 'WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS'], $names);
    }

    public function testSetsComputeOnGtidSets(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT GTID_SUBSET('3e11fa47-71ca-11e1-9e33-c80aa9429562:1-5', '3e11fa47-71ca-11e1-9e33-c80aa9429562:1-10'), GTID_SUBTRACT('3e11fa47-71ca-11e1-9e33-c80aa9429562:1-10', '3e11fa47-71ca-11e1-9e33-c80aa9429562:3-4'), GTID_SUBSET(NULL, ''), GTID_SUBTRACT('', '')")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertSame([['1', '3e11fa47-71ca-11e1-9e33-c80aa9429562:1-2:5-10', null, '']], $result->rows);
        self::assertSame([[Field::LongLong, 21, 1], [Field::VarString, 1896, 0], [Field::LongLong, 21, 0], [Field::LongBlob, 67108864, 0]], array_map(static fn ($column): array => [$column->type, $column->length, $column->flags & 1], $result->columns));
    }

    public function testSetsRefuseAMalformedSet(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Malformed GTID set specification 'x'.");

        (new Instance())->connect()->query("SELECT GTID_SUBTRACT('', 'x')");
    }

    public function testSetsTakeNoTagsBefore83(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Malformed GTID set specification '3e11fa47-71ca-11e1-9e33-c80aa9429562:a:1'.");

        (new Instance('8.0.44'))->connect()->query("SELECT GTID_SUBTRACT('3e11fa47-71ca-11e1-9e33-c80aa9429562:a:1', '')");
    }

    public function testExecutedRefusesTheWaitWithGtidModeOff(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Cannot use WAIT_FOR_EXECUTED_GTID_SET when GTID_MODE = OFF.');

        (new Instance())->connect()->query("SELECT WAIT_FOR_EXECUTED_GTID_SET('', 1)");
    }

    public function testExecutedRefusesANullSetFirst(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage("Malformed GTID set specification 'NULL'.");

        (new Instance())->connect()->query('SELECT WAIT_FOR_EXECUTED_GTID_SET(NULL, -1)');
    }

    public function testPositionAnswersNullForANamedChannel(): void
    {
        $session = (new Instance())->connect();
        $result = $session->query("SELECT SOURCE_POS_WAIT('', 1), SOURCE_POS_WAIT('f', 'abc', 1, 'ch')")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[[null, null]], [['Warning', '1292', "Truncated incorrect INTEGER value: 'abc'"]]], [$result->rows, $warnings->rows]);
    }

    public function testPositionRefusesACallWithoutChannel(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Multiple channels exist on the replica. Please provide channel name as an argument.');

        (new Instance())->connect()->query("SELECT SOURCE_POS_WAIT('f', 1)");
    }

    public function testPositionRefusesANegativeTimeout(): void
    {
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to SOURCE_POS_WAIT.');

        (new Instance())->connect()->query("SELECT MASTER_POS_WAIT('f', 1, -1, 'ch')");
    }

    public function testPositionAnswersNullIn80AndWarnsIn56(): void
    {
        $modern = (new Instance('8.0.44'))->connect()->query("SELECT SOURCE_POS_WAIT('f', 1)")[0];
        $session = (new Instance('5.6.51'))->connect();
        $legacy = $session->query("SELECT MASTER_POS_WAIT('f', 1, -1)")[0];
        $warnings = $session->query('SHOW WARNINGS')[0];

        self::assertInstanceOf(ResultSet::class, $modern);
        self::assertInstanceOf(ResultSet::class, $legacy);
        self::assertInstanceOf(ResultSet::class, $warnings);
        self::assertSame([[[null]], [[null]], [['Warning', '1210', 'Incorrect arguments to MASTER_POS_WAIT.']]], [$modern->rows, $legacy->rows, $warnings->rows]);
    }

    public function testAppliedAnswersNullOrRefusesANegativeTimeout(): void
    {
        $result = (new Instance('5.7.44'))->connect()->query("SELECT WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS(''), WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS(NULL), WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS('x', 1, 'ch')")[0];
        $legacy = (new Instance('5.6.51'))->connect()->query("SELECT WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS('', -1)")[0];

        self::assertInstanceOf(ResultSet::class, $result);
        self::assertInstanceOf(ResultSet::class, $legacy);
        self::assertSame([[[null, null, null]], [[null]]], [$result->rows, $legacy->rows]);
        $this->expectException(SqlError::class);
        $this->expectExceptionMessage('Incorrect arguments to WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS.');

        (new Instance('8.0.44'))->connect()->query('SELECT WAIT_UNTIL_SQL_THREAD_AFTER_GTIDS(NULL)');
    }
}
