<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Role;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterRoleSetting::class)]
#[Medium]
final class AlterRoleSettingTest extends TestCase
{
    public function testRenderWritesTheRoleTheDatabaseAndTheSetting(): void
    {
        self::assertSame('ALTER ROLE joe IN DATABASE app SET work_mem TO \'4MB\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE joe IN DATABASE app SET work_mem TO \'4MB\'')->toString());
    }

    public function testRenderWritesAllForEveryRole(): void
    {
        self::assertSame('ALTER USER ALL RESET ALL', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER USER ALL RESET ALL')->toString());
    }

    public function testAnalysisKeepsNoRoleForAll(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE ALL IN DATABASE app RESET work_mem')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterRoleSetting::class, $statement);
        self::assertNull($statement->role);
        self::assertSame('app', $statement->database?->value);
    }

    public function testDeriveStatementReportsPublic(): void
    {
        self::assertSame(['role "public" does not exist'], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE public RESET ALL')->facts->diagnostics));
    }

    public function testDeriveStatementAcceptsAll(): void
    {
        self::assertSame([], array_map(static fn (\SqlSemantics\Statement\Fact\Diagnostic $diagnostic): string => $diagnostic->message(), (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER ROLE ALL RESET ALL')->facts->diagnostics));
    }

    public function testRejectsTheGroupWord(): void
    {
        $this->expectExceptionMessage('ALTER GROUP changes members only.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\AlterRoleSetting(\SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord::Group, null, null, new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Role\DropRole(\SqlSemantics\Platform\PostgreSql\Statement\Object\RoleWord::Role, [new \SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpec(\SqlSemantics\Platform\PostgreSql\Statement\Name\RoleSpecKind::Named, new \SqlSemantics\Statement\Identifier\Name('r'))]));
    }
}
