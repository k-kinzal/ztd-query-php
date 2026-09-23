<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(Statement\DropRolesStatement::class)]
#[Medium]
final class DropRolesStatementTest extends TestCase
{
    public function testWithRolesReplacesTheTargetsWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP ROLE 'reader'@'localhost'");
        self::assertInstanceOf(Statement\DropRolesStatement::class, $statement);
        $changed = $statement->withRoles([new AccountName('writer', '%')]);
        self::assertSame('reader', $statement->roles[0]->username);
        self::assertSame("DROP ROLE 'writer' @'%'", $changed->toString());
    }

    public function testWithIfExistsKeepsTheAccountIdentities(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP ROLE 'reader'@'localhost'");
        self::assertInstanceOf(Statement\DropRolesStatement::class, $statement);
        $changed = $statement->withIfExists(true);
        self::assertFalse($statement->ifExists);
        self::assertSame("DROP ROLE IF EXISTS 'reader' @'localhost'", $changed->toString());
    }

    public function testWithOriginRetainsTheCompleteRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP ROLE 'reader'@'localhost'");
        self::assertInstanceOf(Statement\DropRolesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->roles, $copy->roles);
        self::assertSame($statement->ifExists, $copy->ifExists);
    }

    public function testWithOriginRejectsAnotherDatabaseLanguage(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP ROLE 'reader'@'localhost'");
        self::assertInstanceOf(Statement\DropRolesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::Sqlite))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

    #[TestWith(['mysql-5.6.51'])]
    #[TestWith(['mysql-5.7.44'])]
    public function testWithOriginRejectsReleasesWithoutRoles(string $version): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("DROP ROLE 'reader'@'localhost'");
        self::assertInstanceOf(Statement\DropRolesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: $version))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }

}
