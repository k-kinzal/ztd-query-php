<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\SessionRole;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Ownership\ReassignOwnedStatement;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SessionRole::class)]
#[Medium]
final class SessionRoleTest extends TestCase
{
    #[TestWith([SessionRole::CurrentRole])]
    #[TestWith([SessionRole::CurrentUser])]
    #[TestWith([SessionRole::SessionUser])]
    public function testTheLookupRuleSurvivesOwnershipTransferSerialization(SessionRole $role): void
    {
        $binder = new Binder((new SchemaBuilder(Dialect::PostgreSql))->build());
        $statement = $binder->bind('REASSIGN OWNED BY alice TO ' . $role->value);
        self::assertInstanceOf(ReassignOwnedStatement::class, $statement);
        self::assertSame($role, $statement->newOwner);
        $rebound = $binder->bind($statement->toString());
        self::assertInstanceOf(ReassignOwnedStatement::class, $rebound);
        self::assertSame($role, $rebound->newOwner);
    }

}
