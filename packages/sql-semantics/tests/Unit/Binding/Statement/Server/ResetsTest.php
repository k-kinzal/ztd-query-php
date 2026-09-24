<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\Resets;
use SqlSemantics\Dialect;
use SqlSemantics\InvalidSql;
use SqlSemantics\Model\Configuration\Administration\BinaryLogReset;
use SqlSemantics\Model\Configuration\Administration\QueryCacheReset;
use SqlSemantics\Model\Configuration\Administration\ReplicaReset;
use SqlSemantics\Model\Statement\Server\Administration\ResetServerStatement;
use SqlSemantics\Model\Validation\InputViolation;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Resets::class)]
#[Medium]
final class ResetsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'RESET SLAVE ALL, MASTER, QUERY CACHE', 'RESET SLAVE ALL, MASTER, QUERY CACHE'])]
    #[TestWith(['mysql-5.7.44', "RESET SLAVE FOR CHANNEL 'c', MASTER", "RESET SLAVE FOR CHANNEL 'c', MASTER"])]
    #[TestWith(['mysql-8.0.44', 'RESET SLAVE ALL, MASTER TO 3', 'RESET REPLICA ALL, MASTER TO 3'])]
    #[TestWith(['mysql-8.1.0', 'RESET REPLICA, MASTER', 'RESET REPLICA, MASTER'])]
    #[TestWith(['mysql-8.2.0', 'RESET REPLICA, MASTER', 'RESET REPLICA, BINARY LOGS AND GTIDS'])]
    #[TestWith(['mysql-8.3.0', 'RESET SLAVE, BINARY LOGS AND GTIDS', 'RESET REPLICA, BINARY LOGS AND GTIDS'])]
    #[TestWith(['mysql-8.4.7', 'RESET REPLICA ALL, BINARY LOGS AND GTIDS TO 3', 'RESET REPLICA ALL, BINARY LOGS AND GTIDS TO 3'])]
    #[TestWith(['mysql-9.0.1', 'RESET REPLICA', 'RESET REPLICA'])]
    #[TestWith(['mysql-9.1.0', 'RESET BINARY LOGS AND GTIDS', 'RESET BINARY LOGS AND GTIDS'])]
    public function testBindReadsTheOptionsInTheReleaseVocabulary(string $version, string $sql, string $expected): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind($sql);
        self::assertInstanceOf(ResetServerStatement::class, $statement);
        self::assertSame($expected, $statement->toString());
        self::assertSame($expected, $binder->bind($expected)->toString());
    }

    public function testTargetReadsEachOptionKind(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind("RESET SLAVE ALL FOR CHANNEL 'c', MASTER, QUERY CACHE");
        self::assertInstanceOf(ResetServerStatement::class, $statement);
        self::assertInstanceOf(ReplicaReset::class, $statement->targets[0]);
        self::assertTrue($statement->targets[0]->all);
        self::assertSame('c', $statement->targets[0]->channel);
        self::assertInstanceOf(BinaryLogReset::class, $statement->targets[1]);
        self::assertInstanceOf(QueryCacheReset::class, $statement->targets[2]);
    }

    public function testTargetDiagnosesAnIndexOutOfRange(): void
    {
        $this->expectExceptionObject(new InvalidSql(InputViolation::BinaryLogIndex, new \SqlParser\Parser\Node('reset_option', 0, [])));
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('RESET BINARY LOGS AND GTIDS TO 0');
    }

    #[TestWith(['mysql-5.7.44', 'reset query cache', 'RESET QUERY CACHE'])]
    #[TestWith(['mysql-5.7.44', 'reset slave all', 'RESET SLAVE ALL'])]
    #[TestWith(['mysql-5.7.44', 'RESET SLAVE', 'RESET SLAVE'])]
    #[TestWith(['mysql-5.7.44', 'reset master', 'RESET MASTER'])]
    #[TestWith(['mysql-8.4.7', 'reset replica all for channel "c"', "RESET REPLICA ALL FOR CHANNEL 'c'"])]
    #[TestWith(['mysql-8.4.7', 'reset binary logs and gtids to 3', 'RESET BINARY LOGS AND GTIDS TO 3'])]
    public function testTargetReadsLowercaseOptions(string $version, string $sql, string $expected): void
    {
        self::assertSame($expected, (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind($sql)->toString());
    }
}
