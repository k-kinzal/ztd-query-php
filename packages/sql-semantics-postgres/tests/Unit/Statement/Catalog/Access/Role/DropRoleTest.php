<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\DropRole::class)]
#[Medium]
final class DropRoleTest extends TestCase
{
    public function testRenderWritesTheWordAndIfExists(): void
    {
        self::assertSame('DROP USER IF EXISTS joe, ann', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP USER IF EXISTS joe, ann')->toString());
    }

    public function testRenderWritesTheGroupWord(): void
    {
        self::assertSame('DROP GROUP staff', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP GROUP staff')->toString());
    }

    public function testDeriveStatementReportsEveryDesignation(): void
    {
        self::assertSame(['cannot use special role specifier in DROP ROLE', 'cannot use special role specifier in DROP ROLE'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('DROP ROLE joe, CURRENT_USER, public')->facts->diagnostics));
    }

    public function testRejectsNoRole(): void
    {
        $this->expectExceptionMessage('A role list names at least one role.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\DropRole(\SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord::Role, []);
    }
}
