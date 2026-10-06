<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultRevoke::class)]
#[Medium]
final class DefaultRevokeTest extends TestCase
{
    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR execute ON ROUTINES FROM joe CASCADE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES REVOKE GRANT OPTION FOR execute ON ROUTINES FROM joe CASCADE')->toString());
    }

    public function testRenderWithoutOptions(): void
    {
        self::assertSame('ALTER DEFAULT PRIVILEGES REVOKE ALL ON SEQUENCES FROM joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES REVOKE ALL ON SEQUENCES FROM joe')->toString());
    }

    public function testAnalysisKeepsTheBehavior(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES REVOKE usage ON SCHEMAS FROM joe RESTRICT')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\AlterDefaultPrivileges::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultRevoke::class, $statement->action);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Option\DropBehavior::Restrict, $statement->action->behavior);
    }

    public function testRejectsNoRole(): void
    {
        $this->expectExceptionMessage('A revoke names at least one role.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultRevoke([], \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultObjectKind::Tables, []);
    }
}
