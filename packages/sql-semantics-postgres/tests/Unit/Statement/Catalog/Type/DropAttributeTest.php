<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\DropAttribute::class)]
#[Medium]
final class DropAttributeTest extends TestCase
{
    public function testRenderWritesIfExists(): void
    {
        self::assertSame('ALTER TYPE pair DROP ATTRIBUTE IF EXISTS b RESTRICT', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE pair DROP ATTRIBUTE IF EXISTS b RESTRICT')->toString());
    }

    public function testDeriveClauseRecordsNothing(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE pair DROP ATTRIBUTE b')->facts->diagnostics);
    }
}
