<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Definition\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Role\RoleSystemId;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Role\CreateRoleStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\SchemaBuilder;

#[CoversClass(RoleSystemId::class)]
#[Medium]
final class RoleSystemIdTest extends TestCase
{
    #[TestWith([0])]
    #[TestWith([5])]
    #[TestWith([2147483647])]
    public function testRetainsAnyUnsignedIdentifierInTheSignedRange(int $id): void
    {
        self::assertSame($id, (new RoleSystemId($id))->id);
    }

    #[TestWith([-1])]
    #[TestWith([2147483648])]
    #[TestWith([PHP_INT_MAX])]
    public function testRejectsANegativeOrOversizedIdentifier(int $id): void
    {
        $this->expectException(InvalidStructure::class);
        new RoleSystemId($id);
    }

    public function testSysidBindsFromADefinition(): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('CREATE ROLE r SYSID 5');
        self::assertInstanceOf(CreateRoleStatement::class, $statement);
        $option = $statement->options[0];
        self::assertInstanceOf(RoleSystemId::class, $option);
        self::assertSame(5, $option->id);
        self::assertSame('CREATE ROLE "r" SYSID 5', $statement->toString());
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(CreateRoleStatement::class, $rebound);
        self::assertSame($statement->toString(), $rebound->toString());
    }
}
