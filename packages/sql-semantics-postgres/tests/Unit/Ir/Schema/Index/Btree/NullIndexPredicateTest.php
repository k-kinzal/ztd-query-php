<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\NullIndexPredicate;

#[CoversClass(NullIndexPredicate::class)]
#[Small]
final class NullIndexPredicateTest extends TestCase
{
    public function testRetainsTheResolvedPositionAndPolarity(): void
    {
        $predicate = new NullIndexPredicate(3, true);

        self::assertSame(3, $predicate->slot);
        self::assertTrue($predicate->negated);
    }
}
