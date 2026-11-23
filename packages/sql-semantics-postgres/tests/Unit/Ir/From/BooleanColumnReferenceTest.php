<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\From\BooleanColumnReference;

#[CoversClass(BooleanColumnReference::class)]
#[Small]
final class BooleanColumnReferenceTest extends TestCase
{
    public function testKeepsTheExactPhysicalCoordinates(): void
    {
        $column = new BooleanColumnReference(2, 1);

        self::assertSame(2, $column->slot);
        self::assertSame(1, $column->occurrence);
    }
}
