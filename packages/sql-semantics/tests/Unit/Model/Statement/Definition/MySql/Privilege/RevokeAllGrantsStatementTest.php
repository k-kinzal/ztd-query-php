<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Definition\MySql\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Account\AccountName;
use SqlSemantics\Model\Statement\Definition\MySql\Privilege\RevokeAllGrantsStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RevokeAllGrantsStatement::class)]
#[Medium]
final class RevokeAllGrantsStatementTest extends TestCase
{
    public function testWithGranteesReplacesTheAccountsWithoutMutatingTheSource(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE ALL, GRANT OPTION FROM u');
        self::assertInstanceOf(RevokeAllGrantsStatement::class, $statement);
        self::assertSame("REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'v'@'h'", $statement->withGrantees([new AccountName('v', 'h')])->toString());
        self::assertEquals([new AccountName('u')], $statement->grantees);
    }

    public function testWithIfExistsAddsTheExistencePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE ALL, GRANT OPTION FROM u');
        self::assertInstanceOf(RevokeAllGrantsStatement::class, $statement);
        self::assertSame("REVOKE IF EXISTS ALL PRIVILEGES, GRANT OPTION FROM 'u'", $statement->withIfExists(true)->toString());
        self::assertFalse($statement->ifExists);
    }

    public function testWithIgnoreUnknownUserAddsTheAccountPolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('REVOKE ALL, GRANT OPTION FROM u');
        self::assertInstanceOf(RevokeAllGrantsStatement::class, $statement);
        self::assertSame("REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'u' IGNORE UNKNOWN USER", $statement->withIgnoreUnknownUser(true)->toString());
        self::assertFalse($statement->ignoreUnknownUser);
    }

    public function testWithIgnoreUnknownUserRejectsTheMySql57Grammar(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.7.44'))->build()))->bind('REVOKE ALL, GRANT OPTION FROM u');
        self::assertInstanceOf(RevokeAllGrantsStatement::class, $statement);
        $this->expectException(InvalidStructure::class);
        $statement->withIgnoreUnknownUser(true);
    }

    public function testWithOriginRetainsTheRevocation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql, grammarVersion: 'mysql-5.6.51'))->build()))->bind('REVOKE ALL PRIVILEGES, GRANT OPTION FROM u, v');
        self::assertInstanceOf(RevokeAllGrantsStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->grantees, $copy->grantees);
    }
}
