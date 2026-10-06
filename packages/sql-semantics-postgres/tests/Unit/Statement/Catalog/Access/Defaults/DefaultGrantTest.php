<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultGrant::class)]
#[Medium]
final class DefaultGrantTest extends TestCase
{
    public function testRenderWritesEveryClause(): void
    {
        self::assertSame('ALTER DEFAULT PRIVILEGES GRANT ALL ON TYPES TO joe, public WITH GRANT OPTION', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES GRANT ALL PRIVILEGES ON TYPES TO GROUP joe, PUBLIC WITH GRANT OPTION')->toString());
    }

    public function testAnalysisKeepsTheKind(): void
    {
        $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES GRANT insert ON TABLES TO joe')->statement;
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\AlterDefaultPrivileges::class, $statement);
        self::assertInstanceOf(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultGrant::class, $statement->action);
        self::assertSame(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultObjectKind::Tables, $statement->action->objects);
        self::assertFalse($statement->action->grantOption);
    }

    public function testRejectsNoGrantee(): void
    {
        $this->expectExceptionMessage('A grant names at least one grantee.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultGrant([], \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\DefaultObjectKind::Tables, []);
    }
}
