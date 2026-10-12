<?php

declare(strict_types=1);

namespace Tests\Unit\Statement\Query\Problem;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Query\Problem\AggregateInOrdering;

#[CoversClass(AggregateInOrdering::class)]
#[Small]
final class AggregateInOrderingTest extends TestCase
{
    public function testMessageNamesTheOrderingPosition(): void
    {
        self::assertSame('Expression #2 of ORDER BY contains aggregate function and applies to the result of a non-aggregated query', (new AggregateInOrdering(2))->message());
    }
    public function testMessageDistinguishesAnUnselectedDistinctAggregate(): void
    {
        self::assertSame('Expression #2 of ORDER BY clause is not in SELECT list, contains aggregate function; this is incompatible with DISTINCT', (new AggregateInOrdering(2, true))->message());
    }

}
