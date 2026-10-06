<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Catalog\Type;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Catalog\Type\EnumPosition::class)]
#[Medium]
final class EnumPositionTest extends TestCase
{
    public function testRenderWritesAfter(): void
    {
        self::assertSame('ALTER TYPE mood ADD VALUE \'x\' AFTER \'y\'', (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('ALTER TYPE mood ADD VALUE \'x\' AFTER \'y\'')->toString());
    }
}
