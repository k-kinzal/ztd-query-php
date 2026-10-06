<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Call\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;

#[CoversClass(AggregateFunction::class)]
#[Small]
final class AggregateFunctionTest extends TestCase
{
    public function testDistinctiveTellsWhetherDistinctIsAccepted(): void
    {
        self::assertTrue(AggregateFunction::Collect->distinctive());
        self::assertFalse(AggregateFunction::BitXor->distinctive());
    }
}
