<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleInherit::class)]
#[Medium]
final class RoleInheritTest extends TestCase
{
    public function testOption(): void
    {
        self::assertSame('inherit', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleInherit())->option());
    }

    public function testRenderWritesTheKeyword(): void
    {
        self::assertSame('CREATE ROLE r INHERIT', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('create role r inherit')->toString());
    }
}
