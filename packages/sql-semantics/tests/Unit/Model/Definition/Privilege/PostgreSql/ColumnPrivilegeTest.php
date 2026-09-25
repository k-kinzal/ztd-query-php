<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Privilege\PostgreSql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\ColumnPrivilege;
use SqlSemantics\Model\Definition\Privilege\PostgreSql\Privilege;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Privilege\GrantPrivilegesStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(ColumnPrivilege::class)]
#[Medium]
final class ColumnPrivilegeTest extends TestCase
{
    public function testReadsTheColumnsOfABoundGrant(): void
    {
        $schema = (new SchemaBuilder(Dialect::PostgreSql))->build('CREATE TABLE t(a INTEGER, b INTEGER)');
        $binder = new Binder($schema);
        $statement = $binder->bind('GRANT SELECT (a, b) ON t TO alice');
        self::assertInstanceOf(GrantPrivilegesStatement::class, $statement);
        $privilege = $statement->privileges[0];
        self::assertInstanceOf(ColumnPrivilege::class, $privilege);
        self::assertSame(Privilege::Select, $privilege->privilege);
        self::assertSame(['a', 'b'], $privilege->columns);
        self::assertSame('GRANT SELECT ("a", "b") ON TABLE "public"."t" TO "alice"', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
        self::assertSame((new \SqlSemantics\SimpleSerializer())->serialize($statement), (new \SqlSemantics\SimpleSerializer())->serialize($binder->bind((new \SqlSemantics\SimpleSerializer())->serialize($statement))));
    }

    #[TestWith([Privilege::Select])]
    #[TestWith([Privilege::Insert])]
    #[TestWith([Privilege::Update])]
    #[TestWith([Privilege::References])]
    #[TestWith([Privilege::All])]
    public function testAcceptsEveryColumnarPrivilege(Privilege $privilege): void
    {
        $column = new ColumnPrivilege($privilege, ['id']);
        self::assertSame($privilege, $column->privilege);
        self::assertSame(['id'], $column->columns);
    }

    #[TestWith([Privilege::Delete])]
    #[TestWith([Privilege::Truncate])]
    #[TestWith([Privilege::Usage])]
    #[TestWith([Privilege::Execute])]
    public function testRejectsAPrivilegeWithoutAColumnForm(Privilege $privilege): void
    {
        $this->expectException(InvalidStructure::class);
        new ColumnPrivilege($privilege, ['id']);
    }

    public function testRejectsAnEmptyColumnName(): void
    {
        $this->expectException(InvalidStructure::class);
        new ColumnPrivilege(Privilege::Select, ['id', '']);
    }
}
