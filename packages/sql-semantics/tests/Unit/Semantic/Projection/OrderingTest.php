<?php

declare(strict_types=1);

namespace Tests\Unit\Semantic\Projection;

use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Dialect;
use Tests\Scenario\SemanticCases;

#[\PHPUnit\Framework\Attributes\CoversClass(\SqlSemantics\Semantic\Projection\Ordering::class)]
#[\PHPUnit\Framework\Attributes\Medium]
final class OrderingTest extends TestCase
{
    public function testToStringPreservesDirectionAndNullPlacement(): void
    {
        $statement = SemanticCases::select(Dialect::Sqlite, 'SELECT foo FROM bar ORDER BY foo DESC NULLS FIRST');
        self::assertTrue($statement->orderBy[0]->descending);
        self::assertTrue($statement->orderBy[0]->nullsFirst);
        self::assertSame('foo DESC NULLS FIRST', $statement->orderBy[0]->toString());
    }
}
