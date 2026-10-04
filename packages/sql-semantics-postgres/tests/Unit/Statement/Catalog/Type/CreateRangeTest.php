<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\CreateRange::class)]
#[Medium]
final class CreateRangeTest extends TestCase
{
    public function testRenderWritesTheAttributes(): void
    {
        self::assertSame('CREATE TYPE r AS RANGE (subtype = float8)', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE r AS RANGE (subtype = float8)')->toString());
    }

    public function testDeriveStatementReportsTheMissingSubtype(): void
    {
        self::assertSame('type attribute "subtype" is required', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('CREATE TYPE floatrange AS RANGE (subtype_diff = float8mi)')->facts->diagnostics[0]->message());
    }
}
