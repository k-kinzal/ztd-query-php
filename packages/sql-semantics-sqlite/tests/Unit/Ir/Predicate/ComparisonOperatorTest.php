<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\ComparisonOperator;

#[CoversClass(ComparisonOperator::class)]
#[Small]
final class ComparisonOperatorTest extends TestCase
{
    public function testClosedSqlOperations(): void
    {
        self::assertSame(['=', '<>', '<', '<=', '>', '>=', 'IS', 'IS NOT'], array_map(
            static fn (ComparisonOperator $kind): string => $kind->value,
            ComparisonOperator::cases(),
        ));
    }
}
