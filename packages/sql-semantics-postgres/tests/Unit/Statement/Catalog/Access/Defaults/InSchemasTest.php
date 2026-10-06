<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Access\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\InSchemas::class)]
#[Medium]
final class InSchemasTest extends TestCase
{
    public function testOption(): void
    {
        self::assertSame('schemas', (new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\InSchemas([new \SqlSemantics\Statement\Identifier\Name('s')]))->option());
    }

    public function testRenderWritesTheSchemas(): void
    {
        self::assertSame('ALTER DEFAULT PRIVILEGES IN SCHEMA a, "B" GRANT SELECT ON TABLES TO joe', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER DEFAULT PRIVILEGES IN SCHEMA a, "B" GRANT SELECT ON TABLES TO joe')->toString());
    }

    public function testRejectsNoSchema(): void
    {
        $this->expectExceptionMessage('IN SCHEMA names at least one schema.');
        new \SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Defaults\InSchemas([]);
    }
}
