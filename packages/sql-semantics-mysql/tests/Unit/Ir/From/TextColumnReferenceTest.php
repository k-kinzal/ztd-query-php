<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\TextColumnReference;

#[CoversClass(TextColumnReference::class)]
#[Small]
final class TextColumnReferenceTest extends TestCase
{
    public function testKeepsItsSlotAndOccurrence(): void
    {
        $reference = new TextColumnReference(2, 1);

        self::assertSame(2, $reference->slot);
        self::assertSame(1, $reference->occurrence);
    }

    public function testRejectsANegativeOccurrence(): void
    {
        $this->expectExceptionMessage('A text column occurrence must be nonnegative.');

        new TextColumnReference(0, -1);
    }
}
