<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Extension\UpdateExtension::class)]
#[Medium]
final class UpdateExtensionTest extends TestCase
{
    public function testRenderWritesTheVersions(): void
    {
        self::assertSame('ALTER EXTENSION hstore UPDATE TO \'2.0\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER EXTENSION hstore UPDATE TO \'2.0\'')->toString());
    }

    public function testDeriveStatementReportsTwoVersions(): void
    {
        self::assertSame('conflicting or redundant options', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER EXTENSION hstore UPDATE TO \'2.0\' TO v')->facts->diagnostics[0]->message());
    }
}
