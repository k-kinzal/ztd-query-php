<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Role\RoleAttribute;
use SqlSemantics\Model\Definition\Role\RoleCapability;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleCapability::class)]
#[Medium]
final class RoleCapabilityTest extends TestCase
{
    #[TestWith([RoleCapability::Superuser, 'SUPERUSER'])]
    #[TestWith([RoleCapability::CreateDb, 'CREATEDB'])]
    #[TestWith([RoleCapability::CreateRole, 'CREATEROLE'])]
    #[TestWith([RoleCapability::Inherit, 'INHERIT'])]
    #[TestWith([RoleCapability::Login, 'LOGIN'])]
    #[TestWith([RoleCapability::Replication, 'REPLICATION'])]
    #[TestWith([RoleCapability::BypassRls, 'BYPASSRLS'])]
    public function testEachCapabilitySpellsItsKeywordAndBindsInBothDirections(RoleCapability $capability, string $keyword): void
    {
        self::assertSame($keyword, $capability->value);
        self::assertSame($capability, RoleCapability::from($keyword));
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $granted = $binder->bind('CREATE ROLE r ' . $keyword);
        self::assertInstanceOf(CreateRoleStatement::class, $granted);
        self::assertEquals([new RoleAttribute($capability, true)], $granted->options);
        self::assertSame('CREATE ROLE "r" ' . $keyword, $granted->toString());
        $withheld = $binder->bind('CREATE ROLE r NO' . $keyword);
        self::assertInstanceOf(CreateRoleStatement::class, $withheld);
        self::assertEquals([new RoleAttribute($capability, false)], $withheld->options);
        self::assertSame('CREATE ROLE "r" NO' . $keyword, $withheld->toString());
        $rebound = $binder->bind($withheld->toString());
        self::assertInstanceOf(CreateRoleStatement::class, $rebound);
        self::assertSame($withheld->toString(), $rebound->toString());
    }

    public function testTheEnumerationListsTheSevenCapabilities(): void
    {
        self::assertCount(7, RoleCapability::cases());
    }
}
