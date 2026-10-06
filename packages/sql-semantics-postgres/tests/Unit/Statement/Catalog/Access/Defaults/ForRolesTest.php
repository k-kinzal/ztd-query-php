<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\ForRoles::class)]
#[Medium]
final class ForRolesTest extends TestCase
{
    public function testOption(): void
    {
        self::assertSame('roles', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\ForRoles(\SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord::User, [new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Statement\Identifier\Name('a'))]))->option());
    }

    public function testRenderKeepsTheWord(): void
    {
        self::assertSame('ALTER DEFAULT PRIVILEGES FOR USER a, CURRENT_USER GRANT SELECT ON TABLES TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES FOR USER a, CURRENT_USER GRANT SELECT ON TABLES TO joe')->toString());
    }

    public function testRejectsTheGroupWord(): void
    {
        $this->expectExceptionMessage('FOR is followed by ROLE or USER.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\ForRoles(\SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord::Group, [new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Statement\Identifier\Name('a'))]);
    }
}
