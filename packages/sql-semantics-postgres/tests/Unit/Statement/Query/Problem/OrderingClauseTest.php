<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;

#[CoversClass(\SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause::class)]
#[Small]
final class OrderingClauseTest extends TestCase
{
    public function testClausesAreSpelled(): void
    {
        self::assertSame(['ORDER BY', 'GROUP BY', 'DISTINCT ON'], array_map(static fn (\SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause $clause): string => $clause->value, \SqlSemantics\Platform\PostgreSql\Statement\Query\Problem\OrderingClause::cases()));
    }
}
