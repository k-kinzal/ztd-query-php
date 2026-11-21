<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Predicate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\Sqlite\Ir\Predicate\LogicalOperator;

#[CoversClass(LogicalOperator::class)]
#[Small]
final class LogicalOperatorTest extends TestCase
{
    public function testClosedSqlOperations(): void
    {
        self::assertSame(['AND', 'OR'], array_map(
            static fn (LogicalOperator $kind): string => $kind->value,
            LogicalOperator::cases(),
        ));
    }
}
