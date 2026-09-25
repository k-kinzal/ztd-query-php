<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Configuration\DiscardStatement;
use SqlSemantics\Model\Statement\Configuration\SetAllConstraintsStatement;
use SqlSemantics\Model\Statement\Notification\ListenStatement;
use SqlSemantics\Model\Statement\Notification\NotifyStatement;
use SqlSemantics\Model\Statement\Notification\UnlistenAllStatement;
use SqlSemantics\Model\Statement\Notification\UnlistenStatement;
use SqlSemantics\Model\Statement\Server\ApplyBinlogStatement;
use SqlSemantics\Model\Statement\Server\CheckpointStatement;
use SqlSemantics\Model\Statement\Server\CloneLocalStatement;
use SqlSemantics\Model\Statement\Server\InstallPluginStatement;
use SqlSemantics\Model\Statement\Server\KillConnectionStatement;
use SqlSemantics\Model\Statement\Server\KillQueryStatement;
use SqlSemantics\Model\Statement\Server\RestartServerStatement;
use SqlSemantics\Model\Statement\Server\ShutdownServerStatement;
use SqlSemantics\Model\Statement\Server\UninstallPluginStatement;
use SqlSemantics\Model\Statement\Server\UnlockTablesStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Session\SessionStatements;

#[CoversClass(SessionStatements::class)]
#[Medium]
final class SessionStatementsTest extends TestCase
{
    #[TestWith([Dialect::PostgreSql, 'CHECKPOINT', 'CHECKPOINT', CheckpointStatement::class])]
    #[TestWith([Dialect::MySql, 'RESTART', 'RESTART', RestartServerStatement::class])]
    #[TestWith([Dialect::MySql, 'SHUTDOWN', 'SHUTDOWN', ShutdownServerStatement::class])]
    #[TestWith([Dialect::MySql, 'UNLOCK TABLES', 'UNLOCK TABLES', UnlockTablesStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'LISTEN events', 'LISTEN "events"', ListenStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'UNLISTEN events', 'UNLISTEN "events"', UnlistenStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'UNLISTEN *', 'UNLISTEN *', UnlistenAllStatement::class])]
    #[TestWith([Dialect::PostgreSql, "NOTIFY events, 'changed'", "NOTIFY \"events\", 'changed'", NotifyStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'NOTIFY events', 'NOTIFY "events"', NotifyStatement::class])]
    #[TestWith([Dialect::MySql, 'KILL CONNECTION 42', 'KILL CONNECTION 42', KillConnectionStatement::class])]
    #[TestWith([Dialect::MySql, 'KILL QUERY 42', 'KILL QUERY 42', KillQueryStatement::class])]
    #[TestWith([Dialect::MySql, "INSTALL PLUGIN audit SONAME 'audit.so'", "INSTALL PLUGIN `audit` SONAME 'audit.so'", InstallPluginStatement::class])]
    #[TestWith([Dialect::MySql, 'UNINSTALL PLUGIN audit', 'UNINSTALL PLUGIN `audit`', UninstallPluginStatement::class])]
    #[TestWith([Dialect::MySql, "CLONE LOCAL DATA DIRECTORY '/tmp/clone'", "CLONE LOCAL DATA DIRECTORY '/tmp/clone'", CloneLocalStatement::class])]
    #[TestWith([Dialect::MySql, "BINLOG 'YWJj'", "BINLOG 'YWJj'", ApplyBinlogStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DISCARD PLANS', 'DISCARD PLANS', DiscardStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'DISCARD ALL', 'DISCARD ALL', DiscardStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'SET CONSTRAINTS ALL DEFERRED', 'SET CONSTRAINTS ALL DEFERRED', SetAllConstraintsStatement::class])]
    #[TestWith([Dialect::PostgreSql, 'SET CONSTRAINTS ALL IMMEDIATE', 'SET CONSTRAINTS ALL IMMEDIATE', SetAllConstraintsStatement::class])]
    public function testWriteSerializesEachSessionOperationFromItsOperands(Dialect $dialect, string $sql, string $expected, string $class): void
    {
        $binder = new Binder((new SchemaBuilder($dialect))->build());
        $statement = $binder->bind($sql);
        self::assertSame($class, $statement::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        $rebound = $binder->bind($expected);
        self::assertSame($class, $rebound::class);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($rebound));
    }

    public function testWriteQuotesTheChannelAndKeepsAnOptionalPayload(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $notify = $binder->bind("NOTIFY events, 'changed'");
        self::assertInstanceOf(NotifyStatement::class, $notify);
        self::assertSame('events', $notify->channel);
        self::assertSame("'changed'", $notify->payload?->spelling());
        self::assertSame("NOTIFY \"events\", 'changed'", SessionStatements::write($notify)->toString());
        $silent = $binder->bind('NOTIFY events');
        self::assertInstanceOf(NotifyStatement::class, $silent);
        self::assertNull($silent->payload);
        self::assertSame('NOTIFY "events"', SessionStatements::write($silent)->toString());
    }

    public function testWriteSpellsTheConnectionIdOfAKillRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('KILL QUERY 42');
        self::assertInstanceOf(KillQueryStatement::class, $statement);
        self::assertSame('42', $statement->connectionId->spelling());
        self::assertSame('KILL QUERY 42', SessionStatements::write($statement)->toString());
    }
}
