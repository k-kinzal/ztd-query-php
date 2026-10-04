<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleAttribute::class)]
#[Medium]
final class RoleAttributeTest extends TestCase
{
    public function testFlagOfAKnownWord(): void
    {
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleFlag::CreateDb, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleAttribute(new \SqlSemantics\Statement\Identifier\Name('createdb')))->flag());
    }

    public function testFlagIsNullForAWordInAnotherLetterCase(): void
    {
        self::assertSame(null, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleAttribute(new \SqlSemantics\Statement\Identifier\Name('LOGIN')))->flag());
    }

    public function testOptionOfAKnownWord(): void
    {
        self::assertSame('canlogin', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleAttribute(new \SqlSemantics\Statement\Identifier\Name('nologin')))->option());
    }

    public function testOptionIsNullForAnUnknownWord(): void
    {
        self::assertSame(null, (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\RoleAttribute(new \SqlSemantics\Statement\Identifier\Name('inherit')))->option());
    }

    public function testRenderQuotesAKeyword(): void
    {
        self::assertSame('CREATE ROLE r "user" superuser', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE ROLE r "user" SUPERUSER')->toString());
    }
}
