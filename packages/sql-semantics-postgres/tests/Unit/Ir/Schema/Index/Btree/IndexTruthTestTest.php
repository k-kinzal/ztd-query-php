<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\TruthOperator;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexTruthTest;

#[CoversClass(IndexTruthTest::class)]
#[Small]
final class IndexTruthTestTest extends TestCase
{
    public function testRetainsTheBoundOperandAndUnknownState(): void
    {
        $predicate = new IndexTruthTest(2, TruthOperator::IsNotUnknown);

        self::assertSame(2, $predicate->slot);
        self::assertSame(TruthOperator::IsNotUnknown, $predicate->operator);
    }
}
