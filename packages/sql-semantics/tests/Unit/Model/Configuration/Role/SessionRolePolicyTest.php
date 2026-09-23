<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\SessionRolePolicy;
use SqlSemantics\SchemaBuilder;

#[CoversClass(SessionRolePolicy::class)]
#[Medium]
final class SessionRolePolicyTest extends TestCase
{
    public function testBindsAnExplicitRolePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind('SET ROLE DEFAULT');
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Role\SetRolePolicyStatement::class, $statement);
        self::assertSame(SessionRolePolicy::Default, $statement->policy);
    }
}
