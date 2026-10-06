<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Catalog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Rules\Catalog\ForeignOptions::class)]
#[Medium]
final class ForeignOptionsTest extends TestCase
{
    public function testWriteOmitsAnEmptyList(): void
    {
        self::assertSame('CREATE SERVER s FOREIGN DATA WRAPPER w', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE SERVER s FOREIGN DATA WRAPPER w')->toString());
    }

    public function testFunctionsAcceptsOneOfEachRole(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE FOREIGN DATA WRAPPER w HANDLER h VALIDATOR v')->facts->diagnostics);
    }
}
