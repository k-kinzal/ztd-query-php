<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Medium;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\PositionOutOfRange::class)]
#[Medium]
final class PositionOutOfRangeTest extends TestCase
{
    public function testMessageNamesTheClauseAndPosition(): void
    {
        $semantics = new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql);
        $query = $semantics->analyze('SELECT 1 GROUP BY 3');
        self::assertSame('GROUP BY position 3 is not in select list', $query->facts->diagnostics[0]->message());
    }
}
