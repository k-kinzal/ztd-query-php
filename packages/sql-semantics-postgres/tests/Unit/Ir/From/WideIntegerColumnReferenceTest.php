<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\WideIntegerColumnReference;

#[CoversClass(WideIntegerColumnReference::class)]
#[Small]
final class WideIntegerColumnReferenceTest extends TestCase
{
    public function testKeepsTheExactPhysicalCoordinates(): void
    {
        $column = new WideIntegerColumnReference(2, 1);

        self::assertSame(2, $column->slot);
        self::assertSame(1, $column->occurrence);
    }
}
