<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\DropOwnedStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\ReassignOwnedStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Configuration\Role\PostgreSqlRoles::class)]
#[Medium]
final class PostgreSqlRolesTest extends TestCase
{
    #[TestWith(['CURRENT_USER', SessionRole::CurrentUser])]
    #[TestWith(['CURRENT_ROLE', SessionRole::CurrentRole])]
    #[TestWith(['SESSION_USER', SessionRole::SessionUser])]
    public function testReadPreservesTheSessionLookup(string $input, SessionRole $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OWNED BY ' . $input);
        self::assertInstanceOf(DropOwnedStatement::class, $statement);
        self::assertSame([$expected], $statement->owners);
    }

    #[TestWith(['Alice', 'alice'])]
    #[TestWith(['"Alice"', 'Alice'])]
    #[TestWith(['"CURRENT_USER"', 'CURRENT_USER'])]
    #[TestWith(['"SESSION_USER"', 'SESSION_USER'])]
    #[TestWith(['"PUBLIC"', 'PUBLIC'])]
    #[TestWith(['"NONE"', 'NONE'])]
    public function testReadRetainsExplicitIdentifiers(string $input, string $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REASSIGN OWNED BY ' . $input . ' TO ' . $input);
        self::assertInstanceOf(ReassignOwnedStatement::class, $statement);
        self::assertInstanceOf(NamedRole::class, $statement->newOwner);
        self::assertEquals([new NamedRole($expected)], $statement->owners);
        self::assertSame($expected, $statement->newOwner->name);
    }

    #[TestWith(['DROP OWNED BY public'])]
    #[TestWith(['DROP OWNED BY "public"'])]
    #[TestWith(['DROP OWNED BY none'])]
    #[TestWith(['REASSIGN OWNED BY alice TO public'])]
    #[TestWith(['REASSIGN OWNED BY alice TO "none"'])]
    public function testReadRejectsReservedOwnershipRoles(string $sql): void
    {
        $this->expectException(\SqlSemantics\InvalidSql::class);
        $this->expectExceptionMessage('ownership selector');
        (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind($sql);
    }

}
