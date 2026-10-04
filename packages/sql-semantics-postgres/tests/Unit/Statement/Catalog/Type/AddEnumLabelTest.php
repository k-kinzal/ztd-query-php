<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AddEnumLabel::class)]
#[Medium]
final class AddEnumLabelTest extends TestCase
{
    public function testRenderWritesIfNotExists(): void
    {
        self::assertSame('ALTER TYPE mood ADD VALUE IF NOT EXISTS \'calm\' BEFORE \'happy\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE mood ADD VALUE IF NOT EXISTS \'calm\' BEFORE \'happy\'')->toString());
    }

    public function testDeriveStatementReportsALongLabel(): void
    {
        self::assertCount(1, (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE mood ADD VALUE \'yyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyyy\'')->facts->diagnostics);
    }
}
