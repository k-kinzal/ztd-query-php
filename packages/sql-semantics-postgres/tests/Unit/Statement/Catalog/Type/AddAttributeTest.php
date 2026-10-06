<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\AddAttribute::class)]
#[Medium]
final class AddAttributeTest extends TestCase
{
    public function testRenderWritesTheCollationAndBehavior(): void
    {
        self::assertSame('ALTER TYPE pair ADD ATTRIBUTE c text COLLATE "C" CASCADE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE pair ADD ATTRIBUTE c text COLLATE "C" CASCADE')->toString());
    }

    public function testDeriveClauseDerivesTheType(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE pair ADD ATTRIBUTE c numeric(4, 2)')->facts->diagnostics);
    }
}
