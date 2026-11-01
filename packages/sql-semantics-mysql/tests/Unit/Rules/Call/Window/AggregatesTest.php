<?php

declare(strict_types=1);

namespace Tests\Unit\Rules\Call\Window;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Aggregates;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateKind;

#[CoversClass(Aggregates::class)]
#[Small]
final class AggregatesTest extends TestCase
{
    public function testLimitationDistinguishesInertDistinctFromUnsupportedInvocations(): void
    {
        $rules = new Aggregates();
        self::assertNull($rules->limitation(AggregateFunction::Minimum, true));
        self::assertNull($rules->limitation(AggregateFunction::Maximum, true));
        self::assertNull($rules->limitation(AggregateFunction::Collect, true));
        self::assertNull($rules->limitation(AggregateFunction::Sum, false));
        self::assertSame(WindowingLimit::DistinctAggregate, $rules->limitation(AggregateFunction::Sum, true));
        self::assertSame(WindowingLimit::DistinctAggregate, $rules->limitation(AggregateFunction::Average, true));
        self::assertSame(WindowingLimit::DistinctAggregate, $rules->limitation(AggregateFunction::Count, true));
        self::assertSame(WindowingLimit::GroupConcat, $rules->limitation(DeferredAggregateKind::GroupConcat, false));
    }
}
