<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Definition\Role\ClearedPassword;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ClearedPassword::class)]
#[Medium]
final class ClearedPasswordTest extends TestCase
{
    public function testAClearedPasswordCarriesNoSecret(): void
    {
        self::assertEquals(new ClearedPassword(), new ClearedPassword());
        self::assertSame([], get_object_vars(new ClearedPassword()));
    }

    public function testPasswordNullBindsAsAClearedPassword(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER ROLE r PASSWORD NULL');
        self::assertInstanceOf(AlterRoleStatement::class, $statement);
        self::assertCount(1, $statement->options);
        self::assertInstanceOf(ClearedPassword::class, $statement->options[0]);
        self::assertSame('ALTER ROLE "r" PASSWORD NULL', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(AlterRoleStatement::class, $rebound);
        self::assertSame($statement->toString(), $rebound->toString());
    }

    public function testAClearedPasswordSerializesInsideADefinition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $origin = $binder->bind('SELECT 1')->origin;
        $statement = new CreateRoleStatement($origin, new NamedRole('r'), [new ClearedPassword()]);
        self::assertSame('CREATE ROLE "r" PASSWORD NULL', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateRoleStatement::class, $rebound);
        self::assertInstanceOf(ClearedPassword::class, $rebound->options[0]);
        self::assertSame($statement->toString(), $rebound->toString());
    }
}
