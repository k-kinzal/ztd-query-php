<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Server\BinaryLogs;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsBeforeStatement;
use SqlSemantics\Model\Statement\Server\Replication\PurgeBinaryLogsToStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(BinaryLogs::class)]
#[Medium]
final class BinaryLogsTest extends TestCase
{
    #[TestWith(['mysql-5.6.51', 'MASTER'])]
    #[TestWith(['mysql-5.7.44', 'BINARY'])]
    #[TestWith(['mysql-8.0.44', 'MASTER'])]
    #[TestWith(['mysql-8.1.0', 'BINARY'])]
    #[TestWith(['mysql-8.2.0', 'MASTER'])]
    #[TestWith(['mysql-8.3.0', 'BINARY'])]
    #[TestWith(['mysql-8.4.7', 'BINARY'])]
    #[TestWith(['mysql-9.0.1', 'BINARY'])]
    #[TestWith(['mysql-9.1.0', 'BINARY'])]
    public function testPurgeBindsTheLogNameOnEveryRelease(string $version, string $spelling): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind('PURGE ' . $spelling . " LOGS TO 'binlog.000007'");
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $statement);
        self::assertSame("'binlog.000007'", $statement->logName->text);
        self::assertSame("PURGE BINARY LOGS TO 'binlog.000007'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertInstanceOf(PurgeBinaryLogsToStatement::class, $rebound);
        self::assertSame($statement->logName->text, $rebound->logName->text);
    }

    public function testPurgeBindsTheCutOffExpression(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("PURGE BINARY LOGS BEFORE '2024-01-01' ");
        self::assertInstanceOf(PurgeBinaryLogsBeforeStatement::class, $statement);
        self::assertSame("'2024-01-01'", $statement->moment->spelling());
        self::assertSame("PURGE BINARY LOGS BEFORE '2024-01-01'", (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }
}
