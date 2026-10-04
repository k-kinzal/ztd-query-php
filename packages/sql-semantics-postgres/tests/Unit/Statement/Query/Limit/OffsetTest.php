<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Limit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\Offset::class)]
#[Small]
final class OffsetTest extends TestCase
{
    public function testRenderDropsTheNoiseWord(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 OFFSET 2 ROW');
        self::assertSame('SELECT 1 OFFSET 2', $query->toString());
    }
}
