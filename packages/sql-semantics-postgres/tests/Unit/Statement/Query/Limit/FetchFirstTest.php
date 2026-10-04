<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Limit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Limit\FetchFirst::class)]
#[Small]
final class FetchFirstTest extends TestCase
{
    public function testRenderWritesTheStandardSpelling(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 ORDER BY 1 FETCH NEXT +3 ROW WITH TIES');
        self::assertSame('SELECT 1 ORDER BY 1 FETCH FIRST 3 ROWS WITH TIES', $query->toString());
    }

    public function testRenderWritesANegativeCount(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 FETCH FIRST -1 ROWS ONLY');
        self::assertSame('SELECT 1 FETCH FIRST - 1 ROWS ONLY', $query->toString());
    }
}
