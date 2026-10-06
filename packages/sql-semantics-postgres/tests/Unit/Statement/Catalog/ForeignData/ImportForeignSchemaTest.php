<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\ForeignData;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\ForeignData\ImportForeignSchema::class)]
#[Medium]
final class ImportForeignSchemaTest extends TestCase
{
    public function testRenderWritesTheRequest(): void
    {
        self::assertSame('IMPORT FOREIGN SCHEMA remote LIMIT TO (a, b) FROM SERVER s INTO local OPTIONS (x \'y\')', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('IMPORT FOREIGN SCHEMA remote LIMIT TO (a, b) FROM SERVER s INTO local OPTIONS (x \'y\')')->toString());
    }

    public function testDeriveStatementDeclaresNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('IMPORT FOREIGN SCHEMA r FROM SERVER s INTO l')->declarations());
    }
}
