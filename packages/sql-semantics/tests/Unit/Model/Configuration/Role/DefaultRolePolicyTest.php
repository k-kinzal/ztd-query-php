<?php

declare(strict_types=1);

namespace Tests\Unit\Model\Configuration\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Binder;
use SqlSemantics\Dialect;
use SqlSemantics\Model\Configuration\Role\DefaultRolePolicy;
use SqlSemantics\SchemaBuilder;

#[CoversClass(DefaultRolePolicy::class)]
#[Medium]
final class DefaultRolePolicyTest extends TestCase
{
    public function testBindsAnExplicitRolePolicy(): void
    {
        $statement = (new Binder((new SchemaBuilder(Dialect::MySql))->build()))->bind("SET DEFAULT ROLE ALL TO 'u'");
        self::assertInstanceOf(\SqlSemantics\Model\Statement\Configuration\Role\SetDefaultRolePolicyStatement::class, $statement);
        self::assertSame(DefaultRolePolicy::All, $statement->policy);
    }
}
