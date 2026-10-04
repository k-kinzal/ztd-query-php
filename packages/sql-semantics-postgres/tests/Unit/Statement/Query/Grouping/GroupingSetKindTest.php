<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Grouping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSetKind::class)]
#[Small]
final class GroupingSetKindTest extends TestCase
{
    public function testKindsAreSpelled(): void
    {
        self::assertSame(['', 'ROLLUP', 'CUBE', 'GROUPING SETS'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSetKind $kind): string => $kind->value, \SqlSemantics\Platform\PostgreSql\Statement\Query\Grouping\GroupingSetKind::cases()));
    }
}
