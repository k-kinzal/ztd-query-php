<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Owned;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Owned\ReassignOwned::class)]
#[Medium]
final class ReassignOwnedTest extends TestCase
{
    public function testRenderWritesTheOwners(): void
    {
        self::assertSame('REASSIGN OWNED BY joe, SESSION_USER TO boss', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REASSIGN OWNED BY joe, SESSION_USER TO boss')->toString());
    }

    public function testDeriveStatementReportsPublic(): void
    {
        self::assertSame(['role "public" does not exist', 'role "public" does not exist'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REASSIGN OWNED BY PUBLIC TO PUBLIC')->facts->diagnostics));
    }

    public function testRejectsNoOwner(): void
    {
        $this->expectExceptionMessage('A role list names at least one role.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Owned\ReassignOwned([], new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Statement\Identifier\Name('boss')));
    }
}
