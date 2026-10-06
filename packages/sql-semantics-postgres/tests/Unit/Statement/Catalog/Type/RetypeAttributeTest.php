<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\RetypeAttribute::class)]
#[Medium]
final class RetypeAttributeTest extends TestCase
{
    public function testRenderDropsSetData(): void
    {
        self::assertSame('ALTER TYPE pair ALTER ATTRIBUTE a TYPE int8 COLLATE x CASCADE', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE pair ALTER ATTRIBUTE a SET DATA TYPE int8 COLLATE x CASCADE')->toString());
    }

    public function testDeriveClauseDerivesTheType(): void
    {
        self::assertSame([], (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE pair ALTER ATTRIBUTE a TYPE varchar(10)')->facts->diagnostics);
    }
}
