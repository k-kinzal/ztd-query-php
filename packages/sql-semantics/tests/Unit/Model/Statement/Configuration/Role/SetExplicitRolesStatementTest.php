<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Statement\Configuration\Role\SetExplicitRolesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetExplicitRolesStatement::class)]
#[Medium]
final class SetExplicitRolesStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRoleOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET ROLE 'r'");
        self::assertInstanceOf(SetExplicitRolesStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame([], $copy->assignments());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET ROLE 'r'");
        self::assertInstanceOf(SetExplicitRolesStatement::class, $statement);
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new SetExplicitRolesStatement($origin, $statement->roles);
    }

    public function testWithRolesRebindsANewSnapshot(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind("SET ROLE 'r'");
        self::assertInstanceOf(SetExplicitRolesStatement::class, $statement);
        $changed = $statement->withRoles([new AccountName('other', 'localhost')]);
        self::assertSame('other', $changed->roles[0]->username);
        self::assertSame('localhost', $changed->roles[0]->host);
        self::assertSame("SET ROLE 'r'", $statement->toString());
        self::assertSame($changed->toString(), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($changed->toString())));
    }

}
