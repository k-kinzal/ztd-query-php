<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Role\AllRoles;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\AlterRoleSetStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(AllRoles::class)]
#[Medium]
final class AllRolesTest extends TestCase
{
    public function testTheSelectionSpellsTheAllKeyword(): void
    {
        self::assertSame('ALL', AllRoles::All->value);
        self::assertSame(AllRoles::All, AllRoles::from('ALL'));
        self::assertSame([AllRoles::All], AllRoles::cases());
    }

    public function testTheSelectionStandsForEveryRoleInAStoredSetting(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('ALTER ROLE ALL SET work_mem TO 1');
        self::assertInstanceOf(AlterRoleSetStatement::class, $statement);
        self::assertSame(AllRoles::All, $statement->role);
        self::assertSame('ALTER ROLE ALL SET "work_mem" = 1', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(AlterRoleSetStatement::class, $rebound);
        self::assertSame(AllRoles::All, $rebound->role);
        self::assertSame($statement->toString(), $rebound->toString());
    }
}
