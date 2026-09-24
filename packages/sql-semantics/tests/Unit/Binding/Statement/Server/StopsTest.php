<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Stops;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Replication\GapsClosed;
use SqlSemantics\Model\Configuration\Replication\GtidUntil;
use SqlSemantics\Model\Configuration\Replication\SourcePosition;
use SqlSemantics\Model\Statement\Server\Replication\StartReplicaStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Stops::class)]
#[Medium]
final class StopsTest extends TestCase
{
    public function testReadKeepsTheLastValueOfARepeatedCoordinate(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind("START SLAVE UNTIL MASTER_LOG_FILE = 'a', MASTER_LOG_POS = 4, MASTER_LOG_FILE = 'b'");
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf(SourcePosition::class, $statement->until);
        self::assertSame("'b'", $statement->until->file->text);
        self::assertSame("START SLAVE UNTIL MASTER_LOG_FILE = 'b', MASTER_LOG_POS = 4", $statement->toString());
    }

    #[TestWith(["START REPLICA UNTIL SOURCE_LOG_FILE = 'a'"])]
    #[TestWith(["START REPLICA UNTIL SOURCE_LOG_FILE = 'a', SOURCE_LOG_POS = 4, RELAY_LOG_FILE = 'r', RELAY_LOG_POS = 4"])]
    #[TestWith(["START REPLICA UNTIL SQL_BEFORE_GTIDS = 'g', SOURCE_LOG_POS = 4"])]
    public function testReadDiagnosesAnIncompleteOrMixedStopPoint(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }

    public function testConditionChoosesTheStopPoint(): void
    {
        self::assertInstanceOf(GapsClosed::class, Stops::condition([], null, null, true));
        self::assertNull(Stops::condition([], GtidUntil::Before, null, true));
        self::assertNull(Stops::condition([], null, null, false));
    }

    public function testCoordinateTreatsMasterAndSourceAlike(): void
    {
        self::assertSame('source-file', Stops::coordinate('MASTER_LOG_FILE'));
        self::assertSame('source-position', Stops::coordinate('SOURCE_LOG_POS'));
        self::assertSame('relay-file', Stops::coordinate('RELAY_LOG_FILE'));
        self::assertSame('relay-position', Stops::coordinate('RELAY_LOG_POS'));
    }

    /**
     * @param class-string<object> $class
     */
    #[TestWith(["start replica until source_log_file = 'a', source_log_pos = 4", SourcePosition::class, "START REPLICA UNTIL SOURCE_LOG_FILE = 'a', SOURCE_LOG_POS = 4"])]
    #[TestWith(["START REPLICA UNTIL RELAY_LOG_FILE = 'r', RELAY_LOG_POS = 4", \SqlSemantics\Model\Configuration\Replication\RelayPosition::class, "START REPLICA UNTIL RELAY_LOG_FILE = 'r', RELAY_LOG_POS = 4"])]
    #[TestWith(["start replica until sql_before_gtids = 'g'", \SqlSemantics\Model\Configuration\Replication\GtidBoundary::class, "START REPLICA UNTIL SQL_BEFORE_GTIDS = 'g'"])]
    #[TestWith(["START REPLICA UNTIL SQL_AFTER_GTIDS = 'g'", \SqlSemantics\Model\Configuration\Replication\GtidBoundary::class, "START REPLICA UNTIL SQL_AFTER_GTIDS = 'g'"])]
    #[TestWith(['START REPLICA UNTIL SQL_AFTER_MTS_GAPS', GapsClosed::class, 'START REPLICA UNTIL SQL_AFTER_MTS_GAPS'])]
    public function testReadReadsEachStopPoint(string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertInstanceOf($class, $statement->until);
        self::assertSame($expected, $statement->toString());
    }

    public function testReadReturnsNullWithoutAnUntilClause(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('START REPLICA');
        self::assertInstanceOf(StartReplicaStatement::class, $statement);
        self::assertNull($statement->until);
    }

    #[TestWith(["START REPLICA UNTIL SOURCE_LOG_FILE = 'a', SOURCE_LOG_POS = 4, RELAY_LOG_FILE = 'r'"])]
    #[TestWith(["START REPLICA UNTIL RELAY_LOG_FILE = 'r', RELAY_LOG_POS = 4, SOURCE_LOG_FILE = 'a'"])]
    #[TestWith(["START REPLICA UNTIL RELAY_LOG_FILE = 'r'"])]
    public function testReadDiagnosesAPartialCoordinateBesideACompleteOne(string $sql): void
    {
        $this->expectException(InvalidSql::class);
        $this->expectExceptionMessage('START REPLICA UNTIL requires one complete source or relay log position, a GTID set, or SQL_AFTER_MTS_GAPS alone.');
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }
}
