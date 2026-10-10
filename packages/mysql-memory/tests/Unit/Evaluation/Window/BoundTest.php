<?php

declare(strict_types=1);

namespace Tests\Unit\Evaluation\Window;

use MySqlMemory\Evaluation\Window\Bound;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;

#[CoversClass(Bound::class)]
#[Small]
final class BoundTest extends TestCase
{
    public function testRowsCountsAnOffsetFromTheCurrentRow(): void
    {
        self::assertSame([1, 4, 0, 2, 7], [
            (new Bound(FrameBoundKind::Preceding, 1))->rows(2, 8),
            (new Bound(FrameBoundKind::Following, 2))->rows(2, 8),
            (new Bound(FrameBoundKind::UnboundedPreceding))->rows(2, 8),
            (new Bound(FrameBoundKind::CurrentRow))->rows(2, 8),
            (new Bound(FrameBoundKind::UnboundedFollowing))->rows(2, 8),
        ]);
    }

    public function testRowsLiesOutsideThePartitionForAnOffsetPastItsEdge(): void
    {
        self::assertSame([-1, 8], [(new Bound(FrameBoundKind::Preceding, PHP_INT_MAX))->rows(2, 8), (new Bound(FrameBoundKind::Following, PHP_INT_MAX))->rows(2, 8)]);
    }
}
