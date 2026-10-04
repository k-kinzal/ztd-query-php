<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Privilege;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\RevokeRole::class)]
#[Medium]
final class RevokeRoleTest extends TestCase
{
    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('REVOKE inherit OPTION FOR staff FROM joe GRANTED BY boss CASCADE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REVOKE "inherit" OPTION FOR staff FROM joe GRANTED BY boss CASCADE')->toString());
    }

    public function testRenderWithoutAnOption(): void
    {
        self::assertSame('REVOKE staff, sales FROM joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REVOKE staff, sales FROM joe')->toString());
    }

    public function testAnalysisKeepsTheOption(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REVOKE ADMIN OPTION FOR staff FROM joe')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\RevokeRole::class, $statement);
        self::assertSame('admin', $statement->option?->value);
        self::assertNull($statement->behavior);
    }

    public function testDeriveStatementReportsTheProblems(): void
    {
        self::assertSame(['column names cannot be included in GRANT/REVOKE ROLE', 'role "public" does not exist', 'unrecognized role option "foo"'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('REVOKE foo OPTION FOR staff (a) FROM PUBLIC')->facts->diagnostics));
    }

    public function testRejectsNoMember(): void
    {
        $this->expectExceptionMessage('A role revoke names at least one member.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\RevokeRole([new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Privilege\Privilege(new \SqlSemantics\Statement\Identifier\Name('staff'))], []);
    }
}
