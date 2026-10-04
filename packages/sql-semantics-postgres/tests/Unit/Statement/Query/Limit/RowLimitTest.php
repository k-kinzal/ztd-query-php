<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Limit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\RowLimit::class)]
#[Small]
final class RowLimitTest extends TestCase
{
    public function testRenderKeepsTheOffsetFirst(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 OFFSET 1 FETCH FIRST ROWS ONLY');
        self::assertSame('SELECT 1 OFFSET 1 FETCH FIRST ROWS ONLY', $query->toString());
    }
}
