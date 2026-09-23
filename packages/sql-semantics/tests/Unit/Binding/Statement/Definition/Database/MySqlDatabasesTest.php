<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Database\CurrentDatabase;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Definition\Database\DatabaseCollation;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;
use SqlSemantics\Model\Statement\Definition\MySql\AlterDatabaseStatement;
use SqlSemantics\Model\Statement\Definition\MySql\CreateDatabaseStatement;
use SqlSemantics\Model\Statement\Definition\MySql\UpgradeDatabaseDirectoryStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Database\MySqlDatabases::class)]
#[Medium]
final class MySqlDatabasesTest extends TestCase
{
    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    #[TestWith(['mysql-8.0.44'])]
    #[TestWith(['mysql-8.1.0'])]
    #[TestWith(['mysql-8.2.0'])]
    #[TestWith(['mysql-8.3.0'])]
    #[TestWith(['mysql-8.4.7'])]
    #[TestWith(['mysql-9.0.1'])]
    #[TestWith(['mysql-9.1.0'])]
    public function testBindDatabaseDefaultsAcrossAllMySqlReleases(string $version): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build());
        $creation = $binder->bind('CREATE SCHEMA IF NOT EXISTS app CHARACTER SET utf8mb4 COLLATE utf8mb4_bin');
        $alteration = $binder->bind('ALTER SCHEMA app CHARACTER SET utf8mb4');
        self::assertInstanceOf(CreateDatabaseStatement::class, $creation);
        self::assertInstanceOf(AlterDatabaseStatement::class, $alteration);
        self::assertTrue($creation->ifNotExists);
        self::assertSame('app', $creation->name);
        self::assertCount(2, $creation->options);
        self::assertCount(1, $alteration->options);
        self::assertInstanceOf(DatabaseCharacterSet::class, $creation->options[0]);
        self::assertInstanceOf(DatabaseCollation::class, $creation->options[1]);
        self::assertSame('utf8mb4', $creation->options[0]->name);
        self::assertSame('utf8mb4_bin', $creation->options[1]->name);
    }

    public function testBindOmittedDatabaseResolvesAgainstTheSnapshot(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, 'app'))->build()))->bind('ALTER DATABASE READ ONLY DEFAULT');
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        self::assertSame('app', $statement->name);
        self::assertSame([DatabaseReadOnly::Disabled], $statement->options);
    }

    public function testBindOmittedDatabaseRetainsASymbolicReferenceWithoutASuppliedDefault(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('ALTER DATABASE READ ONLY 1');
        self::assertInstanceOf(AlterDatabaseStatement::class, $statement);
        self::assertSame(CurrentDatabase::Session, $statement->name);
        self::assertSame([DatabaseReadOnly::Enabled], $statement->options);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testBindLegacyUpgradeHasNoDefaultOptionPayload(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('ALTER DATABASE old UPGRADE DATA DIRECTORY NAME');
        self::assertInstanceOf(UpgradeDatabaseDirectoryStatement::class, $statement);
        self::assertSame('old', $statement->name);
    }

    #[TestWith(['CREATE DATABASE ``'])]
    #[TestWith(['ALTER DATABASE `` READ ONLY 0'])]
    #[TestWith(['DROP DATABASE ``'])]
    public function testBindEmptyDatabaseNameIsAnInvalidRequest(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind($sql);
    }
}
