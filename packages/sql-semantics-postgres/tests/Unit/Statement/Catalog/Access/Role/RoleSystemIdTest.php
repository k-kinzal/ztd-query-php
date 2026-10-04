<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleSystemId::class)]
#[Medium]
final class RoleSystemIdTest extends TestCase
{
    public function testOptionIsNull(): void
    {
        self::assertSame(null, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleSystemId(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('100')))->option());
    }

    public function testRenderWritesTheIdentifier(): void
    {
        self::assertSame('CREATE ROLE r SYSID 100', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE r SYSID 100')->toString());
    }
}
