<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Key;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\MySql\Ir\Schema\Key\PrimaryKeyColumn;

#[CoversClass(PrimaryKeyColumn::class)]
#[Small]
final class PrimaryKeyColumnTest extends TestCase
{
    public function testRetainsItsResolvedSlotAndDirection(): void
    {
        $term = new PrimaryKeyColumn(2, true);

        self::assertSame(2, $term->slot);
        self::assertTrue($term->descending);
    }
}
