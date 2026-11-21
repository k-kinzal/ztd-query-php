<?php

declare(strict_types=1);

namespace Tests\Unit\Ir;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;

#[CoversClass(ColumnReference::class)]
#[Small]
final class ColumnReferenceTest extends TestCase
{
    public function testAcceptsSlotZero(): void
    {
        self::assertSame(0, (new ColumnReference(0))->slot);
    }

    public function testRejectsNegativeSlot(): void
    {
        $this->expectExceptionMessage('nonnegative');

        new ColumnReference(-1);
    }
}
