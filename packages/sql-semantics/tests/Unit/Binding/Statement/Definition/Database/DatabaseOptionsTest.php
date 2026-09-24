<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Definition\Database\DatabaseCollation;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;
use SqlSemantics\Model\Definition\Database\ServerCharacterInheritance;
use SqlSemantics\Model\Statement\Definition\MySql\AlterDatabaseStatement;
use SqlSemantics\Model\Statement\Definition\MySql\CreateDatabaseStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Database\DatabaseOptions::class)]
#[Medium]
final class DatabaseOptionsTest extends TestCase
{
    public function testCreationRetainsOrderedDefaultRequests(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE DATABASE app ENCRYPTION 'Y' CHARACTER SET utf8mb4 COLLATE utf8mb4_bin ENCRYPTION 'N'");
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        self::assertSame(DatabaseEncryption::Enabled, $statement->options[0]);
        self::assertInstanceOf(DatabaseCharacterSet::class, $statement->options[1]);
        self::assertInstanceOf(DatabaseCollation::class, $statement->options[2]);
        self::assertSame(DatabaseEncryption::Disabled, $statement->options[3]);
    }

    public function testInitialKeepsLegacyServerInheritanceExplicit(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('CREATE DATABASE app CHARACTER SET DEFAULT COLLATE DEFAULT');
        self::assertInstanceOf(CreateDatabaseStatement::class, $statement);
        self::assertInstanceOf(DatabaseCharacterSet::class, $statement->options[0]);
        self::assertInstanceOf(DatabaseCollation::class, $statement->options[1]);
        self::assertSame(ServerCharacterInheritance::Inherit, $statement->options[0]->name);
        self::assertSame(ServerCharacterInheritance::Inherit, $statement->options[1]->name);
    }

    public function testAlterationKeepsIdenticalRepeatedAccessRequests(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER DATABASE app READ ONLY DEFAULT READ ONLY 0');
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        self::assertSame([DatabaseReadOnly::Disabled, DatabaseReadOnly::Disabled], $statement->options);
    }

    public function testAlterationRejectsContradictoryAccessRequests(): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER DATABASE app READ ONLY DEFAULT READ ONLY 1');
    }

    public function testBindReadsEveryLowerCaseDefault(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $create = $binder->bind("create database d character set utf8mb4 collate utf8mb4_bin encryption 'Y'");
        $alter = $binder->bind('alter database d collate utf8mb4_bin');
        self::assertInstanceOf(CreateDatabaseStatement::class, $create);
        self::assertInstanceOf(AlterDatabaseStatement::class, $alter);
        self::assertSame("CREATE DATABASE `d` CHARACTER SET `utf8mb4` COLLATE `utf8mb4_bin` ENCRYPTION 'Y'", $create->toString());
        self::assertSame('ALTER DATABASE `d` COLLATE `utf8mb4_bin`', $alter->toString());
    }

    public function testInitialReadsAParsedCollation(): void
    {
        $option = \SqlSemantics\Ast\Tree::outer((new \SqlSemantics\Ast\DialectParser(Dialect::MySql))->parse('CREATE DATABASE d COLLATE utf8mb4_bin'), ['create_database_option'])[0];
        $initial = \SqlSemantics\Binding\Statement\Definition\Database\DatabaseOptions::initial($option, new \SqlSemantics\Ast\Identifiers(Dialect::MySql));
        self::assertInstanceOf(DatabaseCollation::class, $initial);
    }
}
