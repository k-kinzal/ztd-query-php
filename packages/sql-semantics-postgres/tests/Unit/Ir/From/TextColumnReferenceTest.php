<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\TextColumnReference;

#[CoversClass(TextColumnReference::class)]
#[Small]
final class TextColumnReferenceTest extends TestCase
{
    public function testKeepsBothCoordinates(): void
    {
        $reference = new TextColumnReference(3, 1);

        self::assertSame(3, $reference->slot);
        self::assertSame(1, $reference->occurrence);
    }

    public function testRejectsAnUnavailableCoordinate(): void
    {
        $this->expectExceptionMessage('A table occurrence must be nonnegative.');

        new TextColumnReference(0, -1);
    }
}
