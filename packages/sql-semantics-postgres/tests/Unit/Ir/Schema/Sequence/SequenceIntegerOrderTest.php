<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Expression\WideIntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\IntegerStorage;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Sequence\SequenceIntegerOrder;

#[CoversClass(SequenceIntegerOrder::class)]
final class SequenceIntegerOrderTest extends TestCase
{
    public function testLimitsPreservesTheExactSignedTypeWidth(): void
    {
        [$minimum, $maximum] = SequenceIntegerOrder::limits(IntegerStorage::Wide);

        self::assertSame('-9223372036854775808', $minimum->decimal);
        self::assertSame('9223372036854775807', $maximum->decimal);
    }

    public function testCompareOrdersBothSignedEndpointsExactly(): void
    {
        $minimum = new WideIntegerLiteral('-9223372036854775808');
        $next = new WideIntegerLiteral('-9223372036854775807');
        $maximum = new WideIntegerLiteral('9223372036854775807');

        self::assertLessThan(0, SequenceIntegerOrder::compare($minimum, $next));
        self::assertLessThan(0, SequenceIntegerOrder::compare($next, $maximum));
        self::assertGreaterThan(0, SequenceIntegerOrder::compare($maximum, $minimum));
        self::assertSame(0, SequenceIntegerOrder::compare($maximum, $maximum));
    }
}
