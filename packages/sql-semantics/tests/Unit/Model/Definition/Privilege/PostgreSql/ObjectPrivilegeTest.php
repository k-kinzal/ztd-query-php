<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ObjectPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ObjectPrivilege::class)]
#[Medium]
final class ObjectPrivilegeTest extends TestCase
{
    #[TestWith([Privilege::Usage])]
    #[TestWith([Privilege::Delete])]
    #[TestWith([Privilege::All])]
    public function testRetainsThePrivilegeWithoutJudgingTheObjectClass(Privilege $privilege): void
    {
        self::assertSame($privilege, (new ObjectPrivilege($privilege))->privilege);
    }

    public function testReadsThePrivilegesOfABoundGrantInRequestOrder(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('GRANT CONNECT, TEMP ON DATABASE d TO a');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        self::assertEquals([new ObjectPrivilege(Privilege::Connect), new ObjectPrivilege(Privilege::Temporary)], $statement->privileges);
        self::assertSame('GRANT CONNECT, TEMPORARY ON DATABASE "d" TO "a"', $statement->toString());
        self::assertSame($statement->toString(), $binder->bind($statement->toString())->toString());
    }
}
