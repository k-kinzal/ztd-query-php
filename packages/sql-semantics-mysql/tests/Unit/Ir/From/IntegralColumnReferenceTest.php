<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;

#[CoversClass(IntegralColumnReference::class)]
#[Small]
final class IntegralColumnReferenceTest extends TestCase
{
    public function testResolvedPositionKeepsItsOccurrence(): void
    {
        $reference = new IntegralColumnReference(4, 2);

        self::assertSame(4, $reference->slot);
        self::assertSame(2, $reference->occurrence);
    }
}
