<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Account;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Statement\Definition\MySql\Account\CreateRolesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(CreateRolesStatement::class)]
#[Medium]
final class CreateRolesStatementTest extends TestCase
{
    public function testWithRolesReplacesTheRolesWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE ROLE reader');
        self::assertInstanceOf(CreateRolesStatement::class, $statement);
        $changed = $statement->withRoles([new AccountName('writer', 'h')]);
        self::assertSame('reader', $statement->roles[0]->username);
        self::assertSame("CREATE ROLE 'writer'@'h'", $changed->toString());
    }

    public function testWithIfNotExistsKeepsTheRoles(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE ROLE reader');
        self::assertInstanceOf(CreateRolesStatement::class, $statement);
        $changed = $statement->withIfNotExists(true);
        self::assertFalse($statement->ifNotExists);
        self::assertTrue($changed->ifNotExists);
        self::assertSame("CREATE ROLE IF NOT EXISTS 'reader'", $changed->toString());
    }

    public function testWithOriginRetainsTheRequest(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("CREATE ROLE IF NOT EXISTS reader, 'writer'@'h'");
        self::assertInstanceOf(CreateRolesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->roles, $copy->roles);
        self::assertTrue($copy->ifNotExists);
    }

    public function testWithOriginRejectsAMySql57Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('CREATE ROLE reader');
        self::assertInstanceOf(CreateRolesStatement::class, $statement);
        $origin = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('SELECT 1')->origin;
        $this->expectException(InvalidStructure::class);
        $statement->withOrigin($origin);
    }
}
