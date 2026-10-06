<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterGroupMembers::class)]
#[Medium]
final class AlterGroupMembersTest extends TestCase
{
    public function testRenderWritesTheActionAndTheMembers(): void
    {
        self::assertSame('ALTER GROUP staff ADD USER joe, ann', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER GROUP staff ADD USER joe, ann')->toString());
    }

    public function testAnalysisKeepsTheAction(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER GROUP staff DROP USER joe')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterGroupMembers::class, $statement);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Option\AddOrDrop::Drop, $statement->action);
        self::assertCount(1, $statement->members);
    }

    public function testDeriveStatementReportsPublic(): void
    {
        self::assertSame(['role "public" does not exist', 'role "public" does not exist'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER GROUP public ADD USER public, pg_x')->facts->diagnostics));
    }

    public function testDeriveStatementReportsAReservedGroup(): void
    {
        self::assertSame(['role name "pg_x" is reserved'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER GROUP pg_x ADD USER a')->facts->diagnostics));
    }

    public function testRejectsNoMember(): void
    {
        $this->expectExceptionMessage('A role list names at least one role.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterGroupMembers(new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Statement\Identifier\Name('g')), \SqlSemantics\Platform\PostgreSql\Statement\Option\AddOrDrop::Add, []);
    }
}
