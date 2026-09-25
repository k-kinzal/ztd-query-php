<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Statement\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;
use SqlSemantics\Model\Statement\Configuration\Role\SetRolePolicyStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SetRolePolicyStatement::class)]
#[Medium]
final class SetRolePolicyStatementTest extends TestCase
{
    public function testWithOriginRetainsTheRoleOperation(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET ROLE NONE');
        self::assertInstanceOf(SetRolePolicyStatement::class, $statement);
        $copy = $statement->withOrigin($statement->origin);
        self::assertNotSame($statement, $copy);
        self::assertSame($statement->toString(), $copy->toString());
        self::assertSame([], $copy->assignments());
    }

    public function testRejectsAnotherDialect(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET ROLE NONE');
        self::assertInstanceOf(SetRolePolicyStatement::class, $statement);
        $origin = new \SqlSemantics\Model\Statement\Origin('s0', $statement->source, Dialect::Sqlite);
        $this->expectException(\SqlSemantics\Model\Validation\InvalidStructure::class);
        new SetRolePolicyStatement($origin, $statement->policy);
    }

    public function testWithPolicyRebindsANewSnapshot(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::MySql))->build());
        $statement = $binder->bind('SET ROLE NONE');
        self::assertInstanceOf(SetRolePolicyStatement::class, $statement);
        $changed = $statement->withPolicy(SessionRolePolicy::Default);
        self::assertSame(SessionRolePolicy::Default, $changed->policy);
        self::assertSame('SET ROLE NONE', $statement->toString());
        self::assertSame($changed->toString(), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind($changed->toString())));
    }
}
