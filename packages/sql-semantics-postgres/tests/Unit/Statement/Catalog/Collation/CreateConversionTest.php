<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Collation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Collation\CreateConversion::class)]
#[Medium]
final class CreateConversionTest extends TestCase
{
    public function testRenderWritesDefault(): void
    {
        self::assertSame('CREATE DEFAULT CONVERSION myconv FOR \'UTF8\' TO \'LATIN1\' FROM myfunc', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE DEFAULT CONVERSION myconv FOR \'UTF8\' TO \'LATIN1\' FROM myfunc')->toString());
    }

    public function testDeriveStatementRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE CONVERSION s.c FOR \'UTF8\' TO \'LATIN1\' FROM s.f')->facts->diagnostics);
    }
}
