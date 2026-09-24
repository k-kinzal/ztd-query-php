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
        self::assertSame("INSTALL COMPONENT 'a' SET PERSIST `v` = 1", $statement->toString());
        self::assertSame("UNINSTALL COMPONENT 'a'", $binder->bind("UNINSTALL COMPONENT 'a'")->toString());
    }

    public function testInstanceWritesTheActionAndOmitsTheDefaultChannel(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('ALTER INSTANCE RELOAD TLS FOR CHANNEL mysql_main');
        self::assertInstanceOf(ReloadTlsStatement::class, $statement);
        self::assertSame('alter-instance', AdministrationCommands::instance($statement)->role);
        self::assertSame('ALTER INSTANCE RELOAD TLS', $statement->toString());
    }

    public function testCloneWritesTheDonorAndCredentials(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CLONE INSTANCE FROM u@h:1 IDENTIFIED BY 'p'");
        self::assertInstanceOf(CloneRemoteStatement::class, $statement);
        self::assertSame('clone-instance', AdministrationCommands::clone($statement)->role);
        self::assertSame("CLONE INSTANCE FROM 'u' @'h' : 1 IDENTIFIED BY 'p'", $statement->toString());
    }
}
