<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleCapability;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleAttribute::class)]
#[Medium]
final class RoleAttributeTest extends TestCase
{
    public function testRetainsTheCapabilityAndItsDirection(): void
    {
        $withheld = new RoleAttribute(RoleCapability::Login, false);
        self::assertSame(RoleCapability::Login, $withheld->capability);
        self::assertFalse($withheld->granted);
        $granted = new RoleAttribute(RoleCapability::Superuser, true);
        self::assertSame(RoleCapability::Superuser, $granted->capability);
        self::assertTrue($granted->granted);
    }

    public function testTheNoPrefixBindsAsTheWithheldForm(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ROLE r NOLOGIN SUPERUSER');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        self::assertEquals([new RoleAttribute(RoleCapability::Login, false), new RoleAttribute(RoleCapability::Superuser, true)], $statement->options);
        self::assertSame('CREATE ROLE "r" NOLOGIN SUPERUSER', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateRoleStatement::class, $rebound);
        self::assertSame($statement->toString(), $rebound->toString());
    }
}
