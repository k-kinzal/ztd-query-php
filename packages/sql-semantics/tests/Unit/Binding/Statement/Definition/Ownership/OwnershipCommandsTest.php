<?php

declare(strict_types=1);

namespace Tests\Unit\Binding\Statement\Definition\Ownership;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\NamedRole;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Definition\DropBehavior;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\DropOwnedStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\ReassignOwnedStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(\SqlSemantics\Binding\Statement\Definition\Ownership\OwnershipCommands::class)]
#[Medium]
final class OwnershipCommandsTest extends TestCase
{
    public function testBindSeparatesTheDestinationFromTheSourceOwners(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('REASSIGN OWNED BY alice, CURRENT_USER, "SESSION_USER" TO CURRENT_ROLE');
        self::assertInstanceOf(ReassignOwnedStatement::class, $statement);
        self::assertEquals([new NamedRole('alice'), SessionRole::CurrentUser, new NamedRole('SESSION_USER')], $statement->owners);
        self::assertSame(SessionRole::CurrentRole, $statement->newOwner);
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Reassign, $statement->kind);
    }

    #[TestWith(['', DropBehavior::Default])]
    #[TestWith(['CASCADE', DropBehavior::Cascade])]
    #[TestWith(['RESTRICT', DropBehavior::Restrict])]
    public function testBindRetainsTheRemovalPolicy(string $policy, DropBehavior $expected): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('DROP OWNED BY CURRENT_USER, "CASCADE" ' . $policy);
        self::assertInstanceOf(DropOwnedStatement::class, $statement);
        self::assertSame($expected, $statement->behavior);
        self::assertEquals([SessionRole::CurrentUser, new NamedRole('CASCADE')], $statement->owners);
        self::assertSame(\SqlSemantics\Model\Statement\StatementKind::Drop, $statement->kind);
    }

    public function testBindReadsALowerCaseDropBehavior(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::PostgreSql))->build()))->bind('drop owned by r cascade');
        self::assertInstanceOf(DropOwnedStatement::class, $statement);
        self::assertSame(DropBehavior::Cascade, $statement->behavior);
        self::assertSame('DROP OWNED BY "r" CASCADE', (new \SqlSemantics\SimpleSerializer())->serialize($statement));
    }
}
