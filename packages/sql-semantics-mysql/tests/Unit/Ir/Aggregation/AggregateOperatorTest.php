<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Aggregation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Aggregation\AggregateOperator;

#[CoversClass(AggregateOperator::class)]
#[Small]
final class AggregateOperatorTest extends TestCase
{
    public function testDefinesTheClosedBuiltinRoles(): void
    {
        self::assertSame(['count', 'sum', 'min', 'max'], array_map(static fn (AggregateOperator $case): string => $case->value, AggregateOperator::cases()));
    }
}
