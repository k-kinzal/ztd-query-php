<?php

declare(strict_types=1);

namespace Tests\Unit\Ir\Schema\Index\Btree;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\TestCase;
use SqlSemantics\Platform\PostgreSql\Ir\Schema\Index\Btree\IncludedColumn;

#[CoversClass(IncludedColumn::class)]
#[Small]
final class IncludedColumnTest extends TestCase
{
    public function testStoresThePhysicalSlot(): void
    {
        self::assertSame(4, (new IncludedColumn(4))->slot);
    }
}
