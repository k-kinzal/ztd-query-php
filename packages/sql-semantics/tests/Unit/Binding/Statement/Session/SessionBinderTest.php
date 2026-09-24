<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Session;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Binding\Statement\Session\SessionBinder;
use SqlSemantics\Dialect;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SessionBinder::class)]
#[Medium]
final class SessionBinderTest extends TestCase
{
    /**
     * @param class-string<\SqlSemantics\Model\BoundStatement> $class
     */
    #[TestWith([Dialect::PostgreSql, 'CHECKPOINT', \SqlSemantics\Model\Statement\Server\CheckpointStatement::class])]
    #[TestWith([Dialect::MySql, 'RESTART', \SqlSemantics\Model\Statement\Server\RestartServerStatement::class])]
    #[TestWith([Dialect::MySql, 'SHUTDOWN', \SqlSemantics\Model\Statement\Server\ShutdownServerStatement::class])]
    #[TestWith([Dialect::MySql, 'UNLOCK TABLES', \SqlSemantics\Model\Statement\Server\UnlockTablesStatement::class])]
    public function testBindClassifiesOperandFreeServerActions(Dialect $dialect, string $sql, string $class): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($sql, $statement->toString());
    }

    public function testBindSeparatesQueryAndConnectionKills(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $query = $binder->bind('KILL QUERY 42');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Server\KillQueryStatement::class, $query);
        self::assertInstanceOf(\SqlSemantics\Model\Scalar\Value\Literal::class, $query->connectionId);
        self::assertSame('42', $query->connectionId->text);
        $connection = $binder->bind('KILL 42');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Server\KillConnectionStatement::class, $connection);
        self::assertSame('KILL CONNECTION 42', $connection->toString());
    }

    public function testBindReadsPluginLibraryAndCloneOperands(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $install = $binder->bind("INSTALL PLUGIN p SONAME 'x.so'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Server\InstallPluginStatement::class, $install);
        self::assertSame('p', $install->name);
        self::assertSame("'x.so'", $install->library->text);
        $uninstall = $binder->bind('UNINSTALL PLUGIN p');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Server\UninstallPluginStatement::class, $uninstall);
        self::assertSame('p', $uninstall->name);
        $clone = $binder->bind("CLONE LOCAL DATA DIRECTORY = '/d'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Server\CloneLocalStatement::class, $clone);
        self::assertSame("'/d'", $clone->directory->text);
        self::assertSame("CLONE LOCAL DATA DIRECTORY '/d'", $clone->toString());
    }

    public function testTextReadsTheRequiredLiteralOperand(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("BINLOG 'abc'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Server\ApplyBinlogStatement::class, $statement);
        self::assertSame("'abc'", $statement->encodedEvent->text);
        self::assertSame(\SqlSemantics\Model\Scalar\Value\LiteralKind::Text, $statement->encodedEvent->literalKind);
        self::assertSame(\SqlSemantics\Type\Nullability::NotNull, $statement->encodedEvent->nullability);
    }

    public function testBindFallsBackToNotifications(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('LISTEN ch');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Notification\ListenStatement::class, $statement);
    }
}
