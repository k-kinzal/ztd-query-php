<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\GrantRole::class)]
#[Medium]
final class GrantRoleTest extends TestCase
{
    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('GRANT staff, SELECT, x (c) TO joe, CURRENT_USER WITH admin OPTION GRANTED BY boss', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT staff, SELECT, x (c) TO joe, CURRENT_USER WITH ADMIN OPTION GRANTED BY boss')->toString());
    }

    public function testAnalysisKeepsTheRolesAndMembers(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT staff TO joe, ann')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\GrantRole::class, $statement);
        self::assertSame('staff', $statement->roles[0]->privilege());
        self::assertCount(2, $statement->members);
        self::assertSame([], $statement->options);
    }

    public function testDeriveStatementReportsTheProblems(): void
    {
        self::assertSame(['column names cannot be included in GRANT/REVOKE ROLE', 'role "public" does not exist', 'role "public" does not exist', 'role "public" does not exist', 'unrecognized role option "foo"'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT staff (a), public TO PUBLIC WITH foo TRUE GRANTED BY PUBLIC')->facts->diagnostics));
    }

    public function testDeriveStatementAcceptsTheKnownOptions(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('GRANT staff TO joe WITH ADMIN TRUE, INHERIT FALSE, SET OPTION')->facts->diagnostics));
    }

    public function testRejectsAllAsARole(): void
    {
        $this->expectExceptionMessage('ALL is no role of a role grant.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\GrantRole([new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\PrivilegeKeyword::All, [new \SqlSemantics\Statement\Identifier\Name('a')])], [new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Statement\Identifier\Name('joe'))]);
    }

    public function testRejectsNoMember(): void
    {
        $this->expectExceptionMessage('A role grant names at least one member.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\GrantRole([new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(new \SqlSemantics\Statement\Identifier\Name('staff'))], []);
    }
}
