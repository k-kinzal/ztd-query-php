<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\From;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\From\IntegralSlot;
use SqlSemantics\Platform\MySql\Ir\Schema\IntegerStorage;

#[CoversClass(IntegralSlot::class)]
#[Small]
final class IntegralSlotTest extends TestCase
{
    public function testSelectedFieldRetainsItsWidthAndNullPolicy(): void
    {
        $type = new IntegralSlot(IntegerStorage::Medium, false);

        self::assertSame(IntegerStorage::Medium, $type->storage);
        self::assertFalse($type->nullable);
    }
}
