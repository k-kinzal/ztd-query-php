<?php

declare(strict_types=1);

namespace Tests\Unit\Serialization\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Statement\Server\Administration\CloneRemoteStatement;
use SqlSemantics\Model\Statement\Server\Administration\InstallComponentStatement;
use SqlSemantics\Model\Statement\Server\Administration\ReloadTlsStatement;
use SqlSemantics\SchemaBuilder;
use SqlSemantics\Serialization\Server\AdministrationCommands;

#[CoversClass(AdministrationCommands::class)]
#[Medium]
final class AdministrationCommandsTest extends TestCase
{
    public function testComponentsWritesUrnsAndAssignments(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("INSTALL COMPONENT 'a' SET PERSIST v = 1");
        self::assertInstanceOf(InstallComponentStatement::class, $statement);
        self::assertSame('install-component', AdministrationCommands::components($statement)->role);
        self::assertSame("INSTALL COMPONENT 'a' SET PERSIST `v` = 1", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame("UNINSTALL COMPONENT 'a'", (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind("UNINSTALL COMPONENT 'a'")));
    }

    public function testInstanceWritesTheActionAndOmitsTheDefaultChannel(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('ALTER INSTANCE RELOAD TLS FOR CHANNEL mysql_main');
        self::assertInstanceOf(ReloadTlsStatement::class, $statement);
        self::assertSame('alter-instance', AdministrationCommands::instance($statement)->role);
        self::assertSame('ALTER INSTANCE RELOAD TLS', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    public function testCloneWritesTheDonorAndCredentials(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        self::assertSame('clone-instance', AdministrationCommands::clone($statement)->role);
        self::assertSame("CLONE INSTANCE FROM 'u'@'h':1 IDENTIFIED BY 'p'", (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }

    #[\PHPUnit\Framework\Attributes\TestWith(['alter instance rotate innodb master key', 'ALTER INSTANCE ROTATE INNODB MASTER KEY'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['alter instance rotate binlog master key', 'ALTER INSTANCE ROTATE BINLOG MASTER KEY'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['alter instance reload keyring', 'ALTER INSTANCE RELOAD KEYRING'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['alter instance enable innodb redo_log', 'ALTER INSTANCE ENABLE INNODB REDO_LOG'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['alter instance disable innodb redo_log', 'ALTER INSTANCE DISABLE INNODB REDO_LOG'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['alter instance reload tls for channel mysql_admin no rollback on error', 'ALTER INSTANCE RELOAD TLS FOR CHANNEL `mysql_admin` NO ROLLBACK ON ERROR'])]
    #[\PHPUnit\Framework\Attributes\TestWith(["clone instance from 'u'@'h':3306 identified by 'p' data directory = '/d' require ssl", "CLONE INSTANCE FROM 'u'@'h':3306 IDENTIFIED BY 'p' DATA DIRECTORY = '/d' REQUIRE SSL"])]
    #[\PHPUnit\Framework\Attributes\TestWith(["clone instance from 'u'@'h':3306 identified by 'p' require no ssl", "CLONE INSTANCE FROM 'u'@'h':3306 IDENTIFIED BY 'p' REQUIRE NO SSL"])]
    public function testInstanceWritesEveryInstanceAction(string $sql, string $expected): void
    {
        self::assertSame($expected, (new \SqlSemantics\SimpleSerializer())->serialize((new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind($sql)));
    }


    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.0.44'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-8.4.7'])]
    #[\PHPUnit\Framework\Attributes\TestWith(['mysql-9.1.0'])]
    public function testDonorWritesAnUnspacedAddress(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $statement = $binder->bind("CLONE INSTANCE FROM 'u'@'h':3306 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        self::assertSame("'u'@'h':3306", AdministrationCommands::donor($statement)->toString());
        self::assertSame("CLONE INSTANCE FROM 'u'@'h':3306 IDENTIFIED BY 'p'", (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    public function testDonorWritesTheUnspacedAddress(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-8.4.7'))->build()))->bind("CLONE INSTANCE FROM 'u'@'h':3306 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        self::assertSame("'u'@'h':3306", AdministrationCommands::donor($statement)->toString());
    }
}
