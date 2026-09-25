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
        self::assertSame($sql, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
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
        self::assertSame('KILL CONNECTION 42', (new \SqlSemantics\SimpleSerializer())->serialize($connection));
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
        self::assertSame("CLONE LOCAL DATA DIRECTORY '/d'", (new \SqlSemantics\SimpleSerializer())->serialize($clone));
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

    /**
     * @param class-string<object> $class
     */
    #[TestWith([Dialect::MySql, 'kill query 5', \SqlSemantics\Model\Statement\Server\KillQueryStatement::class, 'KILL QUERY 5'])]
    #[TestWith([Dialect::MySql, 'kill 5', \SqlSemantics\Model\Statement\Server\KillConnectionStatement::class, 'KILL CONNECTION 5'])]
    #[TestWith([Dialect::MySql, 'install plugin p soname \'x.so\'', \SqlSemantics\Model\Statement\Server\InstallPluginStatement::class, 'INSTALL PLUGIN `p` SONAME \'x.so\''])]
    #[TestWith([Dialect::MySql, 'uninstall plugin p', \SqlSemantics\Model\Statement\Server\UninstallPluginStatement::class, 'UNINSTALL PLUGIN `p`'])]
    #[TestWith([Dialect::MySql, 'install component \'file://x\'', \SqlSemantics\Model\Statement\Server\Administration\InstallComponentStatement::class, 'INSTALL COMPONENT \'file://x\''])]
    #[TestWith([Dialect::MySql, 'clone local data directory = \'/d\'', \SqlSemantics\Model\Statement\Server\CloneLocalStatement::class, 'CLONE LOCAL DATA DIRECTORY \'/d\''])]
    #[TestWith([Dialect::MySql, 'CLONE INSTANCE FROM \'u\'@\'h\':3306 IDENTIFIED BY \'p\'', \SqlSemantics\Model\Statement\Server\Administration\CloneRemoteStatement::class, 'CLONE INSTANCE FROM \'u\'@\'h\':3306 IDENTIFIED BY \'p\''])]
    #[TestWith([Dialect::PostgreSql, 'checkpoint', \SqlSemantics\Model\Statement\Server\CheckpointStatement::class, 'CHECKPOINT'])]
    #[TestWith([Dialect::MySql, 'unlock tables', \SqlSemantics\Model\Statement\Server\UnlockTablesStatement::class, 'UNLOCK TABLES'])]
    #[TestWith([Dialect::MySql, 'binlog \'abc\'', \SqlSemantics\Model\Statement\Server\ApplyBinlogStatement::class, 'BINLOG \'abc\''])]
    public function testBindReadsLowerCaseVerbs(Dialect $dialect, string $sql, string $class, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder($dialect))->build()))->bind($sql);
        self::assertInstanceOf($class, $statement);
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testTextReadsTheFirstTextLiteral(): void
    {
        $node = (new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse("BINLOG 'abc'");
        self::assertSame("'abc'", SessionBinder::text($node, new \SqlSemantics\Binding\Scope(new \SqlSemantics\Ast\Identifiers(Dialect::MySql)))->text);
    }
}
