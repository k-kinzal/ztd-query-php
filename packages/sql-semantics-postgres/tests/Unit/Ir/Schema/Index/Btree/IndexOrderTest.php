<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IndexOrder;

#[CoversClass(IndexOrder::class)]
#[Small]
final class IndexOrderTest extends TestCase
{
    public function testEffectiveOrdersPreserveNullPlacement(): void
    {
        self::assertSame('ASC NULLS FIRST', IndexOrder::AscendingNullsFirst->value);
        self::assertSame('DESC NULLS LAST', IndexOrder::DescendingNullsLast->value);
    }
}
